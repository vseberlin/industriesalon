<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_relations_get_place_choices(): array
{
    static $choices = null;

    if (is_array($choices)) {
        return $choices;
    }

    $choices = [];
    $place_type = iss_relations_get_place_post_type();

    if (!post_type_exists($place_type)) {
        return $choices;
    }

    $posts = get_posts([
        'post_type' => $place_type,
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $choices[] = [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
        ];
    }

    return $choices;
}

function iss_relations_add_meta_boxes(): void
{
    foreach (iss_relations_get_supported_post_types() as $post_type) {
        add_meta_box(
            'iss-relations-places',
            __('Verknüpfte Orte', 'iss-relations'),
            'iss_relations_render_meta_box',
            $post_type,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'iss_relations_add_meta_boxes');

function iss_relations_render_row(array $relation, array $places, int $index): string
{
    $roles = iss_relations_get_role_options();
    $place_id = (int) ($relation['place_id'] ?? 0);
    $role = (string) ($relation['role'] ?? 'related');
    $weight = (int) ($relation['weight'] ?? 0);
    $label = (string) ($relation['label'] ?? '');

    ob_start();
    ?>
    <tr data-iss-relations-row>
        <td>
            <select class="widefat" name="iss_relations[<?php echo esc_attr((string) $index); ?>][place_id]">
                <option value=""><?php esc_html_e('Ort wählen', 'iss-relations'); ?></option>
                <?php foreach ($places as $place) : ?>
                    <option value="<?php echo esc_attr((string) $place['id']); ?>" <?php selected($place_id, (int) $place['id']); ?>>
                        <?php echo esc_html((string) $place['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="widefat" name="iss_relations[<?php echo esc_attr((string) $index); ?>][role]">
                <?php foreach ($roles as $value => $label_text) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($role, $value); ?>>
                        <?php echo esc_html($label_text); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input class="small-text" type="number" name="iss_relations[<?php echo esc_attr((string) $index); ?>][weight]" value="<?php echo esc_attr((string) $weight); ?>">
        </td>
        <td>
            <input class="widefat" type="text" name="iss_relations[<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php esc_attr_e('Optionales Anzeige-Label', 'iss-relations'); ?>">
        </td>
        <td>
            <button type="button" class="button-link" data-iss-relations-move="up"><?php esc_html_e('Hoch', 'iss-relations'); ?></button>
            <span aria-hidden="true">|</span>
            <button type="button" class="button-link" data-iss-relations-move="down"><?php esc_html_e('Runter', 'iss-relations'); ?></button>
            <span aria-hidden="true">|</span>
            <button type="button" class="button-link-delete" data-iss-relations-remove><?php esc_html_e('Entfernen', 'iss-relations'); ?></button>
        </td>
    </tr>
    <?php

    return trim((string) ob_get_clean());
}

function iss_relations_render_meta_box(WP_Post $post): void
{
    wp_nonce_field('iss_relations_save_meta', 'iss_relations_meta_nonce');

    $places = iss_relations_get_place_choices();
    $relations = iss_relations_get_post_relations((int) $post->ID);

    echo '<div data-iss-relations-box>';

    if (!$places) {
        echo '<p>' . esc_html__('Noch keine Orte verfügbar. Beziehungen werden aktiv, sobald register_place-Einträge vorhanden sind.', 'iss-relations') . '</p>';
        echo '</div>';
        return;
    }

    echo '<p>' . esc_html__('Verknüpft diesen Inhalt mit einem oder mehreren Orten. Die Auswahl bleibt in Meta gespeichert und wird zusätzlich in eine versteckte Query-Taxonomie gespiegelt.', 'iss-relations') . '</p>';
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Ort', 'iss-relations') . '</th>';
    echo '<th>' . esc_html__('Rolle', 'iss-relations') . '</th>';
    echo '<th>' . esc_html__('Gewicht', 'iss-relations') . '</th>';
    echo '<th>' . esc_html__('Label', 'iss-relations') . '</th>';
    echo '<th>' . esc_html__('Aktionen', 'iss-relations') . '</th>';
    echo '</tr></thead>';
    echo '<tbody data-iss-relations-rows>';

    if ($relations) {
        foreach ($relations as $index => $relation) {
            echo iss_relations_render_row($relation, $places, (int) $index);
        }
    }

    echo '</tbody>';
    echo '</table>';

    echo '<p><button type="button" class="button" data-iss-relations-add>' . esc_html__('Ort hinzufügen', 'iss-relations') . '</button></p>';
    echo '<template data-iss-relations-template>' . iss_relations_render_row([
        'place_id' => 0,
        'role' => 'related',
        'weight' => 0,
        'label' => '',
    ], $places, 9999) . '</template>';
    echo '</div>';
}

function iss_relations_save_meta_box(int $post_id, WP_Post $post): void
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!iss_relations_is_supported_post_type($post->post_type)) {
        return;
    }

    $nonce = $_POST['iss_relations_meta_nonce'] ?? '';
    if (!is_string($nonce) || !wp_verify_nonce($nonce, 'iss_relations_save_meta')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $raw_relations = $_POST['iss_relations'] ?? [];
    $relations = iss_relations_normalize_relations(is_array($raw_relations) ? wp_unslash($raw_relations) : [], $post_id);
    iss_relations_update_post_relations($post_id, $relations);
}
add_action('save_post', 'iss_relations_save_meta_box', 10, 2);

function iss_relations_enqueue_admin_assets(string $hook_suffix): void
{
    if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !iss_relations_is_supported_post_type((string) $screen->post_type)) {
        return;
    }

    $script_path = ISS_RELATIONS_PATH . 'assets/admin-relations.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_enqueue_script(
        'iss-relations-admin',
        ISS_RELATIONS_URL . 'assets/admin-relations.js',
        [],
        (string) filemtime($script_path),
        true
    );
}
add_action('admin_enqueue_scripts', 'iss_relations_enqueue_admin_assets');

