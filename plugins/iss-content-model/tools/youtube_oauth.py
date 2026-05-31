#!/usr/bin/env python3
"""
Local OAuth helper for YouTube Data API.

Uses a Desktop App OAuth client JSON and the installed-app loopback flow.
Stores the resulting token JSON on disk for later scripted channel updates.
"""

from __future__ import annotations

import argparse
import base64
import hashlib
import json
import os
import secrets
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import webbrowser
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path


AUTH_URL = "https://accounts.google.com/o/oauth2/v2/auth"
TOKEN_URL = "https://oauth2.googleapis.com/token"
DEFAULT_SCOPES = ["https://www.googleapis.com/auth/youtube"]


class OAuthCallbackHandler(BaseHTTPRequestHandler):
    server_version = "IndustriesalonYouTubeOAuth/1.0"

    def do_GET(self) -> None:
        parsed = urllib.parse.urlparse(self.path)
        params = urllib.parse.parse_qs(parsed.query)
        self.server.oauth_params = params  # type: ignore[attr-defined]

        if "error" in params:
            body = "OAuth authorization failed. You can return to the terminal."
            status = 400
        else:
            body = "Authorization received. You can return to the terminal."
            status = 200

        encoded = body.encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.send_header("Content-Length", str(len(encoded)))
        self.end_headers()
        self.wfile.write(encoded)

    def log_message(self, format: str, *args) -> None:  # noqa: A003
        return


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Authorize local YouTube Data API access.")
    parser.add_argument(
        "--client-secret",
        required=True,
        help="Path to the Google OAuth Desktop App client JSON.",
    )
    parser.add_argument(
        "--token-file",
        default=str(Path.home() / ".config" / "industriesalon" / "youtube-token.json"),
        help="Where to store the OAuth token JSON.",
    )
    parser.add_argument(
        "--scope",
        action="append",
        dest="scopes",
        help="OAuth scope to request. Repeat to add more. Defaults to full YouTube scope.",
    )
    parser.add_argument(
        "--port",
        type=int,
        default=8765,
        help="Local callback port on localhost.",
    )
    parser.add_argument(
        "--timeout",
        type=int,
        default=240,
        help="Seconds to wait for the browser callback.",
    )
    parser.add_argument(
        "--no-browser",
        action="store_true",
        help="Print the consent URL instead of opening a browser automatically.",
    )
    return parser.parse_args()


def load_client_config(path: str) -> dict:
    with open(path, "r", encoding="utf-8") as handle:
        payload = json.load(handle)

    installed = payload.get("installed")
    if not isinstance(installed, dict):
        raise SystemExit("Client file is not a Desktop App OAuth client.")

    client_id = installed.get("client_id")
    if not client_id:
        raise SystemExit("Client file is missing client_id.")

    return installed


def build_pkce_pair() -> tuple[str, str]:
    verifier = secrets.token_urlsafe(64)
    challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode("ascii")).digest()).decode("ascii").rstrip("=")
    return verifier, challenge


def build_redirect_uri(port: int) -> str:
    return f"http://localhost:{port}/"


def build_auth_url(client_id: str, redirect_uri: str, scopes: list[str], state: str, verifier_challenge: str) -> str:
    query = {
        "client_id": client_id,
        "redirect_uri": redirect_uri,
        "response_type": "code",
        "scope": " ".join(scopes),
        "access_type": "offline",
        "prompt": "consent",
        "include_granted_scopes": "true",
        "state": state,
        "code_challenge": verifier_challenge,
        "code_challenge_method": "S256",
    }
    return AUTH_URL + "?" + urllib.parse.urlencode(query)


def run_callback_server(port: int, timeout: int) -> dict[str, list[str]]:
    server = HTTPServer(("127.0.0.1", port), OAuthCallbackHandler)
    server.timeout = 1
    server.oauth_params = None  # type: ignore[attr-defined]

    deadline = time.time() + timeout
    while time.time() < deadline:
        server.handle_request()
        if server.oauth_params is not None:  # type: ignore[attr-defined]
            params = server.oauth_params  # type: ignore[attr-defined]
            server.server_close()
            return params

    server.server_close()
    raise TimeoutError("Timed out waiting for the OAuth callback.")


def exchange_code(client_id: str, client_secret: str, code: str, verifier: str, redirect_uri: str) -> dict:
    payload = urllib.parse.urlencode(
        {
            "client_id": client_id,
            "client_secret": client_secret,
            "code": code,
            "code_verifier": verifier,
            "grant_type": "authorization_code",
            "redirect_uri": redirect_uri,
        }
    ).encode("utf-8")

    request = urllib.request.Request(
        TOKEN_URL,
        data=payload,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )

    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            return json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")
        raise SystemExit(f"Token exchange failed: HTTP {exc.code}\n{detail}") from exc


def save_token(token_file: str, token_payload: dict, client_id: str, scopes: list[str]) -> None:
    path = Path(token_file).expanduser()
    path.parent.mkdir(parents=True, exist_ok=True)
    material = {
        "client_id": client_id,
        "scopes": scopes,
        "created_at": int(time.time()),
        **token_payload,
    }
    with open(path, "w", encoding="utf-8") as handle:
        json.dump(material, handle, indent=2, ensure_ascii=False)
        handle.write("\n")


def main() -> int:
    args = parse_args()
    config = load_client_config(args.client_secret)
    client_id = config["client_id"]
    client_secret = config.get("client_secret", "")
    scopes = args.scopes or list(DEFAULT_SCOPES)
    redirect_uri = build_redirect_uri(args.port)
    verifier, challenge = build_pkce_pair()
    state = secrets.token_urlsafe(24)
    auth_url = build_auth_url(client_id, redirect_uri, scopes, state, challenge)

    print(f"Using redirect URI: {redirect_uri}")
    print(f"Token file: {Path(args.token_file).expanduser()}")
    print("")
    print("Open this URL in the same browser where the channel manager account is logged in:")
    print(auth_url)
    print("")

    if not args.no_browser:
        opened = webbrowser.open(auth_url)
        if opened:
            print("Opened browser automatically.")
        else:
            print("Could not open browser automatically. Open the URL manually.")

    try:
        params = run_callback_server(args.port, args.timeout)
    except OSError as exc:
        raise SystemExit(f"Could not bind localhost:{args.port}. Pick another --port.\n{exc}") from exc
    except TimeoutError as exc:
        raise SystemExit(str(exc)) from exc

    if params.get("state", [""])[0] != state:
        raise SystemExit("State mismatch. Aborting.")

    if "error" in params:
        raise SystemExit(f"Authorization failed: {params['error'][0]}")

    code = params.get("code", [""])[0]
    if not code:
        raise SystemExit("No authorization code returned.")

    token_payload = exchange_code(client_id, client_secret, code, verifier, redirect_uri)
    save_token(args.token_file, token_payload, client_id, scopes)

    print("")
    print("Authorization succeeded.")
    print(f"Saved token to: {Path(args.token_file).expanduser()}")
    print("Keep this file private. It can be reused for later YouTube update scripts.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
