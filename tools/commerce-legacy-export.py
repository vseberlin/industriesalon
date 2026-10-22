#!/usr/bin/env python3
"""Read local legacy evidence into private review manifests; never modifies a source or site."""
import argparse
import collections
import datetime as dt
import decimal
import gzip
import hashlib
import json
import os
import re
from pathlib import Path
from zoneinfo import ZoneInfo


def values(text):
    """Decode mysqldump VALUES without executing SQL (MySQL backslash escapes)."""
    i = 0
    escapes = {'0': '\0', 'n': '\n', 'r': '\r', 't': '\t', 'b': '\b', 'Z': '\x1a'}
    while i < len(text):
        while i < len(text) and text[i] in ' ,;\r\n':
            i += 1
        if i == len(text):
            return
        if text[i] != '(':
            raise ValueError('Unsupported SQL values framing')
        i += 1
        row = []
        while True:
            while text[i] == ' ':
                i += 1
            if text[i] == "'":
                i += 1
                value = []
                while True:
                    c = text[i]
                    i += 1
                    if c == '\\':
                        c = text[i]
                        i += 1
                        value.append(escapes.get(c, c))
                    elif c == "'":
                        if i < len(text) and text[i] == "'":
                            value.append("'")
                            i += 1
                        else:
                            break
                    else:
                        value.append(c)
                row.append(''.join(value))
            else:
                start = i
                while text[i] not in ',)':
                    i += 1
                raw = text[start:i].strip()
                row.append(None if raw == 'NULL' else raw)
            while text[i] == ' ':
                i += 1
            end = text[i]
            i += 1
            if end == ')':
                yield row
                break
            if end != ',':
                raise ValueError('Unsupported SQL value separator')


def source_hash(path):
    h = hashlib.sha256()
    with path.open('rb') as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b''):
            h.update(block)
    return h.hexdigest()


def private_write(path, data):
    fd = os.open(path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    with os.fdopen(fd, 'w') as out:
        json.dump(data, out, ensure_ascii=False, indent=2)
        out.write('\n')


def extract_bookings(path, as_of):
    bookings, products, metadata = {}, {}, collections.defaultdict(dict)
    schemas = {}
    current = None
    with gzip.open(path, 'rt', encoding='utf-8') as source:
        for line in source:
            match = re.match(r'CREATE TABLE `([^`]+)`', line)
            if match:
                current = match[1]
                schemas[current] = []
            elif current and line.startswith(')'):
                current = None
            elif current and line.lstrip().startswith('`'):
                schemas[current].append(line.split('`')[1])
    def inserts(suffix):
        required = {'ID', 'post_author', 'post_type'} if suffix == '_posts' else {'meta_id', 'post_id', 'meta_key', 'meta_value'}
        candidates = [name for name, cols in schemas.items() if name.endswith(suffix) and required.issubset(cols)]
        if len(candidates) != 1:
            raise ValueError('Cannot resolve unique native WordPress table')
        table = candidates[0]
        with gzip.open(path, 'rt', encoding='utf-8', errors='strict') as source:
            for line in source:
                match = re.match(r"INSERT INTO `([^`]+)` VALUES (.*);\s*$", line)
                if match and match[1] == table:
                    yield from values(match[2])
    for row in inserts('_posts'):
        if len(row) != 23:
            raise ValueError('Unexpected posts schema')
        if row[20] == 'wc_booking':
            bookings[row[0]] = {'legacy_id': row[0], 'status': row[7], 'order_id': row[17]}
        if row[20] == 'product':
            products[row[0]] = row[5]
    for row in inserts('_postmeta'):
        if len(row) != 4:
            raise ValueError('Unexpected postmeta schema')
        if row[1] in bookings and row[2].startswith('_booking_'):
            metadata[row[1]][row[2]] = row[3]
    future = []
    for identity, booking in bookings.items():
        meta = metadata[identity]
        start = meta.get('_booking_start', '')
        if not re.fullmatch(r'\d{14}', start):
            continue
        date = dt.datetime.strptime(start, '%Y%m%d%H%M%S').replace(tzinfo=ZoneInfo('Europe/Berlin'))
        if date.date() < as_of:
            continue
        future.append({**booking, 'start': date.isoformat(), 'product_id': meta.get('_booking_product_id'),
                       'product_title': products.get(meta.get('_booking_product_id'), ''), 'metadata': meta,
                       'reconciled': False})
    return {'booking_count': len(bookings), 'future': future,
            'future_status_counts': dict(collections.Counter(x['status'] for x in future))}


def voucher_manifest(path):
    snapshot = json.loads(path.read_text())
    metadata = collections.defaultdict(dict)
    for row in snapshot['meta']:
        metadata[str(row['post_id'])][row['meta_key']] = row['meta_value']
    digest = source_hash(path)
    result = []
    for post in snapshot['posts']:
        meta = metadata[str(post['ID'])]
        cents = lambda key: int(decimal.Decimal(str(meta[key])) * 100)
        expiry = str(meta.get('_ywgc_expiration', ''))
        expires = dt.datetime.fromtimestamp(int(expiry), dt.timezone.utc).isoformat() if expiry.isdigit() and int(expiry) else None
        result.append({'legacy_site': 'industriesalon.de', 'legacy_id': str(post['ID']), 'code': post['post_title'],
                       'source_sha256': digest, 'amount_cents': cents('_ywgc_amount_total'), 'balance_cents': cents('_ywgc_balance_total'),
                       'currency': meta.get('_ywgc_currency', 'EUR'), 'issued_at': post['post_date'] + ' Europe/Berlin', 'expires_at': expires,
                       'enabled': post['post_status'] == 'publish', 'legacy_status': post['post_status'], 'legacy_order_id': meta.get('_ywgc_order_id'),
                       'legacy_redemption_orders': meta.get('_ywgc_orders', ''), 'reconciled': False, 'single_authority': False, 'terms_verified': False})
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--sql', type=Path, required=True)
    parser.add_argument('--vouchers', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--as-of', type=dt.date.fromisoformat, required=True)
    args = parser.parse_args()
    args.output.mkdir(mode=0o700, parents=True, exist_ok=False)
    bookings = extract_bookings(args.sql, args.as_of)
    vouchers = voucher_manifest(args.vouchers)
    private_write(args.output / 'reservations-review.json', bookings)
    private_write(args.output / 'vouchers-review.json', vouchers)
    summary = {'as_of': str(args.as_of), 'sql_sha256': source_hash(args.sql), 'voucher_source_sha256': source_hash(args.vouchers),
               'legacy_bookings': bookings['booking_count'], 'future_bookings': len(bookings['future']), 'future_status_counts': bookings['future_status_counts'],
               'vouchers': len(vouchers), 'reconciled': False}
    private_write(args.output / 'summary.json', summary)
    print(json.dumps(summary, indent=2))


if __name__ == '__main__':
    main()
