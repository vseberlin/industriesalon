<?php
/** Shared PHP-authored request controls; occurrence and commerce owners retain data/validation. */
if (!defined('ABSPATH')) { exit; }

function iss_programm_booking_context(array $source): array {
    $id = max(0, (int) ($source['source_post_id'] ?? 0));
    $type = sanitize_key((string) ($source['source_post_type'] ?? ''));
    $tag = strtoupper(sanitize_text_field((string) ($source['tag'] ?? '')));
    if ((!$id || !in_array($type, ['fuehrung', 'veranstaltung', 'ausstellung', 'publication'], true)) && $tag !== '') {
        $mapping = iss_occurrences_get_tag_source($tag);
        $id = (int) ($mapping['source_post_id'] ?? 0);
        $type = (string) ($mapping['source_post_type'] ?? '');
    }
    if ($id > 0 && $type === 'fuehrung' && $tag === '') {
        $tag = iss_occurrences_resolve_tag_for_source_post_id($id);
    }
    return [
        'id' => $id, 'type' => $type, 'tag' => $tag,
        'title' => (string) ($source['title'] ?? ($id ? get_the_title($id) : '')),
        'slotId' => (string) ($source['slot_id'] ?? ''),
        'amount' => max(0, (int) ($source['amount'] ?? 0)),
        'intent' => $type === 'publication' ? 'order' : (($source['intent'] ?? '') === 'inquiry' ? 'inquiry' : 'booking'),
    ];
}

function iss_programm_booking_scope(array $source): string {
    return (string) wp_json_encode([(int) $source['id'], (string) $source['type'], (string) $source['tag']]);
}

function iss_programm_booking_trigger_attributes(array $source): array {
    iss_programm_enqueue_calendar_assets();
    return [
        'data-wp-interactive' => 'iss/booking',
        'data-wp-context' => (string) wp_json_encode(['source' => iss_programm_booking_context($source)]),
        'data-wp-on--click' => 'actions.open',
        'aria-haspopup' => 'dialog',
    ];
}

function iss_programm_booking_contact(): string {
    return class_exists('Industriesalon_Steuerung')
        ? Industriesalon_Steuerung::instance()->render_contact('', ['variant' => 'compact-row', 'fields' => ['booking_email', 'email', 'phone']])
        : '';
}

/** One availability picker for the inline block and the shared dialog. */
function iss_programm_render_availability_calendar(?array $source = null): string {
    $id = wp_unique_id('iss-availability-calendar-');
    $context = ['calendar' => ['inline' => $source !== null, 'ready' => false, 'month' => '', 'focusDay' => '', 'day' => '']];
    if ($source !== null) { $context['source'] = $source; }
    ob_start(); ?>
    <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the context attribute. ?>
    <section class="iss-availability-calendar" data-wp-interactive="iss/booking" <?php echo wp_interactivity_data_wp_context($context); ?> data-wp-init="callbacks.initCalendar" data-wp-bind--hidden="!context.calendar.ready" hidden aria-label="<?php esc_attr_e('Verfügbare Termine', 'iss-programm'); ?>">
        <div class="iss-availability-calendar__nav">
            <button type="button" class="iss-availability-calendar__move" data-calendar-step="-1" data-wp-bind--disabled="state.calendarAtStart" data-wp-on--click="actions.moveCalendar" aria-label="<?php esc_attr_e('Vorheriger Monat', 'iss-programm'); ?>">←</button>
            <label class="iss-availability-calendar__month"><span class="screen-reader-text"><?php esc_html_e('Monat und Jahr', 'iss-programm'); ?></span><select data-wp-bind--value="state.calendarMonth" data-wp-on--change="actions.changeCalendarMonth"><template data-wp-each--month="state.calendarMonths" data-wp-each-key="context.month.value"><option data-wp-bind--value="context.month.value" data-wp-bind--selected="context.month.selected" data-wp-text="context.month.label"></option></template></select></label>
            <button type="button" class="iss-availability-calendar__move" data-calendar-step="1" data-wp-bind--disabled="state.calendarAtEnd" data-wp-on--click="actions.moveCalendar" aria-label="<?php esc_attr_e('Nächster Monat', 'iss-programm'); ?>">→</button>
        </div>
        <p id="<?php echo esc_attr($id); ?>" class="screen-reader-text" aria-live="polite" data-wp-text="state.calendarLabel"></p>
        <table class="iss-availability-calendar__grid" role="grid" aria-labelledby="<?php echo esc_attr($id); ?>">
            <thead><tr><?php foreach (['Montag' => 'Mo', 'Dienstag' => 'Di', 'Mittwoch' => 'Mi', 'Donnerstag' => 'Do', 'Freitag' => 'Fr', 'Samstag' => 'Sa', 'Sonntag' => 'So'] as $long => $short) { echo '<th scope="col" abbr="' . esc_attr($long) . '">' . esc_html($short) . '</th>'; } ?></tr></thead>
            <tbody><template data-wp-each--week="state.calendarWeeks" data-wp-each-key="context.week.key"><tr><template data-wp-each--cell="context.week.days" data-wp-each-key="context.cell.key"><td role="gridcell" data-wp-bind--aria-selected="context.cell.selected">
                <button type="button" class="iss-availability-calendar__day" data-wp-bind--hidden="context.cell.empty" data-wp-bind--data-calendar-day="context.cell.value" data-wp-bind--data-availability="context.cell.availability" data-wp-bind--aria-label="context.cell.label" data-wp-bind--aria-disabled="context.cell.disabled" data-wp-bind--aria-pressed="context.cell.selected" data-wp-bind--aria-current="context.cell.current" data-wp-bind--tabindex="context.cell.tabIndex" data-wp-on--click="actions.pickCalendarDay" data-wp-on--keydown="actions.calendarKey" data-wp-on--focus="actions.focusCalendarDay"><span data-wp-text="context.cell.number"></span><span class="iss-availability-calendar__mark" aria-hidden="true" data-wp-text="context.cell.mark"></span></button>
            </td></template></tr></template></tbody>
        </table>
        <p class="iss-availability-calendar__legend"><?php esc_html_e('● Verfügbar · × Ausgebucht / nicht verfügbar', 'iss-programm'); ?></p>
        <p class="iss-availability-calendar__empty" data-wp-bind--hidden="state.calendarHasDates" hidden><?php esc_html_e('In diesem Monat sind keine Termine verfügbar.', 'iss-programm'); ?></p>
        <button type="button" class="iss-availability-calendar__next" data-wp-bind--hidden="state.hideNextAvailable" data-wp-on--click="actions.nextAvailableMonth" hidden><?php esc_html_e('Zum nächsten verfügbaren Termin', 'iss-programm'); ?></button>
        <p class="screen-reader-text"><?php esc_html_e('Mit den Pfeiltasten navigieren. Bild auf/ab wechselt den Monat, mit Umschalt das Jahr. Eingabe wählt einen verfügbaren Tag.', 'iss-programm'); ?></p>
    </section>
    <?php return (string) ob_get_clean();
}

function iss_programm_render_calendar_modal_host(): void {
    static $rendered = false;
    if ($rendered || !did_action('iss_programm_calendar_assets_enqueued')) { return; }
    $rendered = true;
    // The dialog is deliberately outside router regions. All triggers share this
    // shell; public dates and contact/navigation fallbacks stay in server HTML.
    ?>
    <dialog id="iss-booking-dialog" class="is-tour-calendar-modal" data-wp-interactive="iss/booking" aria-labelledby="iss-booking-title" data-wp-on--close="actions.closed" data-wp-on-window--popstate="actions.close">
        <div class="is-tour-calendar-modal__panel">
            <button type="button" class="is-tour-calendar-modal__close" data-wp-on--click="actions.close" aria-label="<?php esc_attr_e('Schließen', 'iss-programm'); ?>">×</button>
            <h2 id="iss-booking-title" data-wp-text="state.title"><?php esc_html_e('Anfrage', 'iss-programm'); ?></h2>
            <p class="is-tour-calendar__status" role="status" aria-live="polite" data-wp-text="state.status"></p>
            <button type="button" data-wp-bind--hidden="state.hideRetry" data-wp-on--click="actions.retrySlots" hidden><?php esc_html_e('Termine erneut laden', 'iss-programm'); ?></button>
            <form class="is-tour-calendar__form" data-wp-on--submit="actions.submit">
                <fieldset class="is-tour-calendar__form-fields" data-wp-bind--disabled="state.fieldsDisabled">
                    <div data-wp-bind--hidden="state.hideSlots">
                        <?php echo iss_programm_render_availability_calendar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes its complete markup. ?>
                        <p class="is-tour-calendar__selected-date" data-wp-text="state.selectedDateLabel"></p>
                        <label class="is-tour-calendar__field"><span><?php esc_html_e('Uhrzeit', 'iss-programm'); ?></span>
                            <select name="slot_id" data-wp-bind--value="state.slotId" data-wp-bind--disabled="state.noDay" data-wp-on--change="actions.selectSlot">
                                <option value=""><?php esc_html_e('Uhrzeit wählen', 'iss-programm'); ?></option>
                                <template data-wp-each--slot="state.times" data-wp-each-key="context.slot.id"><option data-wp-bind--value="context.slot.id" data-wp-bind--disabled="context.slot.disabled" data-wp-text="context.slot.displayLabel"></option></template>
                            </select>
                        </label>
                        <button type="button" data-wp-bind--hidden="state.hideInquirySwitch" data-wp-on--click="actions.chooseInquiry"><?php esc_html_e('Wunschtermin anfragen', 'iss-programm'); ?></button>
                    </div>
                    <div data-wp-bind--hidden="state.hidePreferred" hidden>
                        <p><?php esc_html_e('Ihr Wunschtermin ist eine Anfrage. Wir bestätigen die Verfügbarkeit persönlich.', 'iss-programm'); ?></p>
                        <label class="is-tour-calendar__field"><span><?php esc_html_e('Wunschdatum', 'iss-programm'); ?></span><input name="preferred_date" type="date" min="<?php echo esc_attr(current_time('Y-m-d')); ?>" data-wp-bind--value="state.preferredDate" data-wp-bind--required="state.isInquiry" data-wp-on--input="actions.input"></label>
                        <label class="is-tour-calendar__field"><span><?php esc_html_e('Wunschuhrzeit (optional)', 'iss-programm'); ?></span><input name="preferred_time" type="time" data-wp-bind--value="state.preferredTime" data-wp-on--input="actions.input"></label>
                        <button type="button" data-wp-bind--hidden="state.noSlots" data-wp-on--click="actions.chooseBooking"><?php esc_html_e('Verfügbare Termine wählen', 'iss-programm'); ?></button>
                    </div>
                    <div class="is-tour-calendar__form-grid">
                        <label class="is-tour-calendar__field"><span><?php esc_html_e('Name', 'iss-programm'); ?></span><input required name="name" autocomplete="name" data-wp-bind--value="state.customerName" data-wp-on--input="actions.input"></label>
                        <label class="is-tour-calendar__field"><span><?php esc_html_e('E-Mail', 'iss-programm'); ?></span><input required name="email" type="email" autocomplete="email" data-wp-bind--value="state.email" data-wp-on--input="actions.input"></label>
                        <label class="is-tour-calendar__field" data-wp-bind--hidden="state.isInquiry"><span data-wp-text="state.quantityLabel"><?php esc_html_e('Anzahl', 'iss-programm'); ?></span><input name="quantity" type="number" min="1" value="1" data-wp-bind--max="state.maxQuantity" data-wp-bind--value="state.quantity" data-wp-bind--required="state.needsQuantity" data-wp-on--input="actions.input"></label>
                    </div>
                    <p data-wp-bind--hidden="state.isInquiry" data-wp-text="state.priceLabel"></p>
                    <p><?php esc_html_e('Mit dem Absenden stellen Sie eine Anfrage. Eine Buchung oder Zahlung ist damit noch nicht bestätigt.', 'iss-programm'); ?></p>
                    <input type="text" name="website" tabindex="-1" autocomplete="off" hidden>
                    <button type="submit" class="is-tour-calendar__form-submit" data-wp-bind--disabled="state.submitDisabled"><?php esc_html_e('Anfrage senden', 'iss-programm'); ?></button>
                </fieldset>
            </form>
            <button type="button" class="is-tour-calendar__form-cancel" data-wp-on--click="actions.close"><?php esc_html_e('Schließen', 'iss-programm'); ?></button>
        </div>
    </dialog>
    <?php
}
add_action('wp_footer', 'iss_programm_render_calendar_modal_host', 20);

add_filter('iss_publications_order_button_html', static function ($html, $post_id, $context) {
    if (!function_exists('iss_payments_lite_create_request') || (int) $post_id <= 0) { return $html; }
    $attrs = iss_programm_booking_trigger_attributes(['source_post_id' => (int) $post_id, 'source_post_type' => 'publication', 'title' => $context['title'] ?? get_the_title($post_id), 'amount' => $context['amount'] ?? 0]);
    $id = wp_unique_id('iss-publication-contact-');
    $label = $context['label'] ?? __('Publikation bestellen', 'iss-programm');
    return '<div class="iss-publication-order-panel__actions"><a class="iss-publication-order-trigger" href="#' . esc_attr($id) . '"' . iss_timeline_render_attr_list($attrs) . '>' . esc_html($label) . '</a></div>'
        . '<div id="' . esc_attr($id) . '">' . iss_programm_booking_contact() . '</div>';
}, 10, 3);
