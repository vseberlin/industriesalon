<?php
/**
 * Reviewed website/editor sync, local -> staging, 2026-09-22.
 *
 * Deploy matching code and ops/uploads/2026-09-22-editorial-sync.manifest first.
 * wp eval-file /path/to/this.php --use-include   # read-only preflight
 * wp eval-file /path/to/this.php apply --use-include # guarded one-time application
 * wp eval-file /path/to/this.php verify --use-include # read-only postflight
 * wp eval-file /path/to/this.php validate --use-include # source document validation only
 *
 * 52 documents, their presentation/content prerequisites, three template
 * overrides and 13 missing attachment rows. No private autosaves, Set imports,
 * booking/programme transfers, users, options, credentials or mail settings.
 * Source/target-specific URLs are resolved only inside this payload.
 *
 * Rollback: verified full DB/uploads backup on staging at
 * /srv/industriesalon/stage/backups/20260922-132743/; config/code patch at
 * /home/vladimir/server-actions/sync-20260922/. Exclusive targeted before-image
 * is /tmp/iss-editorial-sync-20260922-before.json in the CLI container; mount
 * a persistent directory at /tmp when applying and retain that file privately.
 * For targeted recovery, restore its existing post/meta/term before-images
 * through WordPress APIs and remove only its recorded newly-created IDs.
 * Rebuild affected plugin projections via their owners. Never replay on a
 * target whose content changed after the recorded preflight.
 */

if (!defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

$mode = $args[0] ?? 'check';
if (!in_array($mode, ['check', 'apply', 'verify', 'validate'], true)) {
    WP_CLI::error('Use check, apply, verify or validate.');
}

$payload = json_decode(<<<'ISS_SYNC_JSON'
{
  "source_url": "http://192.168.2.31:8082",
  "target_url": "https://staging.industriesalon.info",
  "records": [
    {
      "id": 26694,
      "new": true,
      "post": {
        "post_title": "vladimir",
        "post_content": "",
        "post_excerpt": "vladimir",
        "post_status": "inherit",
        "post_name": "vladimir-5",
        "post_type": "attachment",
        "post_parent": 25720,
        "menu_order": 0,
        "post_mime_type": "image/png",
        "post_date": "2026-06-26 09:17:30",
        "post_date_gmt": "2026-06-26 07:17:30"
      },
      "meta": {
        "_wp_attached_file": [
          "event-drop-storage/accepted/projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f.png"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 1672,
            "height": 941,
            "file": "event-drop-storage/accepted/projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f.png",
            "filesize": 1619575,
            "sizes": {
              "medium": {
                "file": "projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f-300x169.png",
                "width": 300,
                "height": 169,
                "mime-type": "image/png",
                "filesize": 42781
              },
              "large": {
                "file": "projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f-1024x576.png",
                "width": 1024,
                "height": 576,
                "mime-type": "image/png",
                "filesize": 476533
              },
              "thumbnail": {
                "file": "projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f-150x150.png",
                "width": 150,
                "height": 150,
                "mime-type": "image/png",
                "filesize": 16777
              },
              "medium_large": {
                "file": "projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f-768x432.png",
                "width": 768,
                "height": 432,
                "mime-type": "image/png",
                "filesize": 270359
              },
              "1536x1536": {
                "file": "projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f-1536x864.png",
                "width": 1536,
                "height": 864,
                "mime-type": "image/png",
                "filesize": 1036156
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26035,
      "new": true,
      "post": {
        "post_title": "phantasy-iss",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "phantasy-iss",
        "post_type": "attachment",
        "post_parent": 12606,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-04 12:09:27",
        "post_date_gmt": "2026-06-04 10:09:27"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/06/phantasy-iss.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 1448,
            "height": 1086,
            "file": "2026/06/phantasy-iss.webp",
            "filesize": 154350,
            "sizes": {
              "medium": {
                "file": "phantasy-iss-300x225.webp",
                "width": 300,
                "height": 225,
                "mime-type": "image/webp",
                "filesize": 13330
              },
              "large": {
                "file": "phantasy-iss-1024x768.webp",
                "width": 1024,
                "height": 768,
                "mime-type": "image/webp",
                "filesize": 90696
              },
              "thumbnail": {
                "file": "phantasy-iss-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 5142
              },
              "medium_large": {
                "file": "phantasy-iss-768x576.webp",
                "width": 768,
                "height": 576,
                "mime-type": "image/webp",
                "filesize": 59700
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26036,
      "new": true,
      "post": {
        "post_title": "jazz-in-iss",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "jazz-in-iss-2",
        "post_type": "attachment",
        "post_parent": 12606,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-04 12:10:23",
        "post_date_gmt": "2026-06-04 10:10:23"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/06/jazz-in-iss-1.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 1448,
            "height": 1086,
            "file": "2026/06/jazz-in-iss-1.webp",
            "filesize": 157702,
            "sizes": {
              "medium": {
                "file": "jazz-in-iss-1-300x225.webp",
                "width": 300,
                "height": 225,
                "mime-type": "image/webp",
                "filesize": 17232
              },
              "large": {
                "file": "jazz-in-iss-1-1024x768.webp",
                "width": 1024,
                "height": 768,
                "mime-type": "image/webp",
                "filesize": 103070
              },
              "thumbnail": {
                "file": "jazz-in-iss-1-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 7606
              },
              "medium_large": {
                "file": "jazz-in-iss-1-768x576.webp",
                "width": 768,
                "height": 576,
                "mime-type": "image/webp",
                "filesize": 69680
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26037,
      "new": true,
      "post": {
        "post_title": "guitar",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "guitar",
        "post_type": "attachment",
        "post_parent": 12606,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-04 12:14:18",
        "post_date_gmt": "2026-06-04 10:14:18"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/06/guitar.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 1448,
            "height": 1086,
            "file": "2026/06/guitar.webp",
            "filesize": 132272,
            "sizes": {
              "medium": {
                "file": "guitar-300x225.webp",
                "width": 300,
                "height": 225,
                "mime-type": "image/webp",
                "filesize": 14058
              },
              "large": {
                "file": "guitar-1024x768.webp",
                "width": 1024,
                "height": 768,
                "mime-type": "image/webp",
                "filesize": 85276
              },
              "thumbnail": {
                "file": "guitar-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 6334
              },
              "medium_large": {
                "file": "guitar-768x576.webp",
                "width": 768,
                "height": 576,
                "mime-type": "image/webp",
                "filesize": 56138
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26034,
      "new": true,
      "post": {
        "post_title": "veranstalltung-raum",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "veranstalltung-raum",
        "post_type": "attachment",
        "post_parent": 12606,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-04 12:08:13",
        "post_date_gmt": "2026-06-04 10:08:13"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/06/veranstalltung-raum.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 1536,
            "height": 1024,
            "file": "2026/06/veranstalltung-raum.webp",
            "filesize": 106540,
            "sizes": {
              "medium": {
                "file": "veranstalltung-raum-300x200.webp",
                "width": 300,
                "height": 200,
                "mime-type": "image/webp",
                "filesize": 10598
              },
              "large": {
                "file": "veranstalltung-raum-1024x683.webp",
                "width": 1024,
                "height": 683,
                "mime-type": "image/webp",
                "filesize": 64130
              },
              "thumbnail": {
                "file": "veranstalltung-raum-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 5376
              },
              "medium_large": {
                "file": "veranstalltung-raum-768x512.webp",
                "width": 768,
                "height": 512,
                "mime-type": "image/webp",
                "filesize": 41914
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26031,
      "new": true,
      "post": {
        "post_title": "kwo-expert",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "kwo-expert",
        "post_type": "attachment",
        "post_parent": 12606,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-04 09:13:17",
        "post_date_gmt": "2026-06-04 07:13:17"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/06/kwo-expert.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 1448,
            "height": 1086,
            "file": "2026/06/kwo-expert.webp",
            "filesize": 163690,
            "sizes": {
              "medium": {
                "file": "kwo-expert-300x225.webp",
                "width": 300,
                "height": 225,
                "mime-type": "image/webp",
                "filesize": 17136
              },
              "large": {
                "file": "kwo-expert-1024x768.webp",
                "width": 1024,
                "height": 768,
                "mime-type": "image/webp",
                "filesize": 98786
              },
              "thumbnail": {
                "file": "kwo-expert-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 7288
              },
              "medium_large": {
                "file": "kwo-expert-768x576.webp",
                "width": 768,
                "height": 576,
                "mime-type": "image/webp",
                "filesize": 67396
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26778,
      "new": true,
      "post": {
        "post_title": "Industriesalon Schöneweide Außenansicht",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "industriesalon-schoneweide-ausenansicht",
        "post_type": "attachment",
        "post_parent": 0,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-29 11:14:49",
        "post_date_gmt": "2026-06-29 09:14:49"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/06/2021-04-22-Sven-Bock-Aussen-Industriesalon-05-hero-scaled.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 2560,
            "height": 1703,
            "file": "2026/06/2021-04-22-Sven-Bock-Aussen-Industriesalon-05-hero-scaled.webp",
            "filesize": 1018166,
            "sizes": {
              "medium": {
                "file": "2021-04-22-Sven-Bock-Aussen-Industriesalon-05-hero-300x200.webp",
                "width": 300,
                "height": 200,
                "mime-type": "image/webp",
                "filesize": 18410
              },
              "large": {
                "file": "2021-04-22-Sven-Bock-Aussen-Industriesalon-05-hero-1024x681.webp",
                "width": 1024,
                "height": 681,
                "mime-type": "image/webp",
                "filesize": 167016
              },
              "thumbnail": {
                "file": "2021-04-22-Sven-Bock-Aussen-Industriesalon-05-hero-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 7834
              },
              "medium_large": {
                "file": "2021-04-22-Sven-Bock-Aussen-Industriesalon-05-hero-768x511.webp",
                "width": 768,
                "height": 511,
                "mime-type": "image/webp",
                "filesize": 96750
              },
              "1536x1536": {
                "file": "2021-04-22-Sven-Bock-Aussen-Industriesalon-05-hero-1536x1022.webp",
                "width": 1536,
                "height": 1022,
                "mime-type": "image/webp",
                "filesize": 373596
              },
              "2048x2048": {
                "file": "2021-04-22-Sven-Bock-Aussen-Industriesalon-05-hero-2048x1362.webp",
                "width": 2048,
                "height": 1362,
                "mime-type": "image/webp",
                "filesize": 669038
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            },
            "original_image": "2021-04-22-Sven-Bock-Aussen-Industriesalon-05-hero.webp"
          }
        ],
        "_wp_attachment_image_alt": [
          "Außenansicht des Industriesalon Schöneweide"
        ],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 27087,
      "new": true,
      "post": {
        "post_title": "Treskowbrücke und Gasanstalt Oberspree, 1926",
        "post_content": "Historische Postkarte: Treskowbrücke 1926, mittig die Gasanstalt Oberspree. Digitaler Ausschnitt aus der BIWAQ-Tafel „Nutzungsmischung aus Tradition – Freizeit- und Gewerbezentrum Spreehöfe“, Stand September 2012. Urheber und ursprünglicher Postkartenverlag sind nicht ermittelt. Quelle: https://www.yumpu.com/de/document/view/2150402/tafeln-1-5-biwaq-schoeneweide/2",
        "post_excerpt": "Treskowbrücke 1926, mittig die Gasanstalt Oberspree.",
        "post_status": "inherit",
        "post_name": "treskowbrucke-und-gasanstalt-oberspree-1926",
        "post_type": "attachment",
        "post_parent": 12899,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-07-19 18:12:00",
        "post_date_gmt": "2026-07-19 16:12:00"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/04/treskowbruecke-gasanstalt-oberspree-1926.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 1521,
            "height": 1034,
            "file": "2026/04/treskowbruecke-gasanstalt-oberspree-1926.webp",
            "filesize": 493150,
            "sizes": {
              "medium": {
                "file": "treskowbruecke-gasanstalt-oberspree-1926-300x204.webp",
                "width": 300,
                "height": 204,
                "mime-type": "image/webp",
                "filesize": 21462
              },
              "large": {
                "file": "treskowbruecke-gasanstalt-oberspree-1926-1024x696.webp",
                "width": 1024,
                "height": 696,
                "mime-type": "image/webp",
                "filesize": 187142
              },
              "thumbnail": {
                "file": "treskowbruecke-gasanstalt-oberspree-1926-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 8826
              },
              "medium_large": {
                "file": "treskowbruecke-gasanstalt-oberspree-1926-768x522.webp",
                "width": 768,
                "height": 522,
                "mime-type": "image/webp",
                "filesize": 121176
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [
          "Historische Ansicht der Treskowbrücke mit Straßenbahn und der Gasanstalt Oberspree im Hintergrund, 1926"
        ],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26695,
      "new": true,
      "post": {
        "post_title": "16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "16-20260626-071807-brand-guidelines_industriesalon-schoneweide",
        "post_type": "attachment",
        "post_parent": 25720,
        "menu_order": 0,
        "post_mime_type": "application/pdf",
        "post_date": "2026-06-26 09:18:24",
        "post_date_gmt": "2026-06-26 07:18:24"
      },
      "meta": {
        "_wp_attached_file": [
          "event-drop-storage/accepted/16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide.pdf"
        ],
        "_wp_attachment_metadata": [
          {
            "sizes": {
              "full": {
                "file": "16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide-pdf.jpg",
                "width": 3413,
                "height": 1920,
                "mime-type": "image/jpeg",
                "filesize": 73041
              },
              "medium": {
                "file": "16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide-pdf-300x169.jpg",
                "width": 300,
                "height": 169,
                "mime-type": "image/jpeg",
                "filesize": 2154
              },
              "large": {
                "file": "16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide-pdf-1024x576.jpg",
                "width": 1024,
                "height": 576,
                "mime-type": "image/jpeg",
                "filesize": 12145
              },
              "thumbnail": {
                "file": "16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide-pdf-150x84.jpg",
                "width": 150,
                "height": 84,
                "mime-type": "image/jpeg",
                "filesize": 923
              }
            },
            "filesize": 151014831
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26605,
      "new": true,
      "post": {
        "post_title": "kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf",
        "post_type": "attachment",
        "post_parent": 26381,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-23 09:23:50",
        "post_date_gmt": "2026-06-23 07:23:50"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/06/kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 1288,
            "height": 1221,
            "file": "2026/06/kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf.webp",
            "filesize": 297764,
            "sizes": {
              "medium": {
                "file": "kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-300x284.webp",
                "width": 300,
                "height": 284,
                "mime-type": "image/webp",
                "filesize": 25596
              },
              "large": {
                "file": "kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-1024x971.webp",
                "width": 1024,
                "height": 971,
                "mime-type": "image/webp",
                "filesize": 196306
              },
              "thumbnail": {
                "file": "kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 8708
              },
              "medium_large": {
                "file": "kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-768x728.webp",
                "width": 768,
                "height": 728,
                "mime-type": "image/webp",
                "filesize": 125370
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26607,
      "new": true,
      "post": {
        "post_title": "1_WFS-1954-16-5f33ff60b3889",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "1_wfs-1954-16-5f33ff60b3889",
        "post_type": "attachment",
        "post_parent": 26381,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-23 10:48:21",
        "post_date_gmt": "2026-06-23 08:48:21"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/06/1_WFS-1954-16-5f33ff60b3889.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 1309,
            "height": 1201,
            "file": "2026/06/1_WFS-1954-16-5f33ff60b3889.webp",
            "filesize": 533800,
            "sizes": {
              "medium": {
                "file": "1_WFS-1954-16-5f33ff60b3889-300x275.webp",
                "width": 300,
                "height": 275,
                "mime-type": "image/webp",
                "filesize": 25404
              },
              "large": {
                "file": "1_WFS-1954-16-5f33ff60b3889-1024x940.webp",
                "width": 1024,
                "height": 940,
                "mime-type": "image/webp",
                "filesize": 346458
              },
              "thumbnail": {
                "file": "1_WFS-1954-16-5f33ff60b3889-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 7404
              },
              "medium_large": {
                "file": "1_WFS-1954-16-5f33ff60b3889-768x705.webp",
                "width": 768,
                "height": 705,
                "mime-type": "image/webp",
                "filesize": 197904
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26609,
      "new": true,
      "post": {
        "post_title": "1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585",
        "post_type": "attachment",
        "post_parent": 26381,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-23 11:32:13",
        "post_date_gmt": "2026-06-23 09:32:13"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/06/1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 1254,
            "height": 1254,
            "file": "2026/06/1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585.webp",
            "filesize": 127160,
            "sizes": {
              "medium": {
                "file": "1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585-300x300.webp",
                "width": 300,
                "height": 300,
                "mime-type": "image/webp",
                "filesize": 17884
              },
              "large": {
                "file": "1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585-1024x1024.webp",
                "width": 1024,
                "height": 1024,
                "mime-type": "image/webp",
                "filesize": 98476
              },
              "thumbnail": {
                "file": "1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 6212
              },
              "medium_large": {
                "file": "1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585-768x768.webp",
                "width": 768,
                "height": 768,
                "mime-type": "image/webp",
                "filesize": 68926
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 26610,
      "new": true,
      "post": {
        "post_title": "1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw",
        "post_type": "attachment",
        "post_parent": 26381,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-23 11:33:25",
        "post_date_gmt": "2026-06-23 09:33:25"
      },
      "meta": {
        "_wp_attached_file": [
          "2026/06/1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw.webp"
        ],
        "_wp_attachment_metadata": [
          {
            "width": 960,
            "height": 959,
            "file": "2026/06/1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw.webp",
            "filesize": 110684,
            "sizes": {
              "medium": {
                "file": "1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw-300x300.webp",
                "width": 300,
                "height": 300,
                "mime-type": "image/webp",
                "filesize": 16102
              },
              "thumbnail": {
                "file": "1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw-150x150.webp",
                "width": 150,
                "height": 150,
                "mime-type": "image/webp",
                "filesize": 5410
              },
              "medium_large": {
                "file": "1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw-768x767.webp",
                "width": 768,
                "height": 767,
                "mime-type": "image/webp",
                "filesize": 74286
              }
            },
            "image_meta": {
              "aperture": "0",
              "credit": "",
              "camera": "",
              "caption": "",
              "created_timestamp": "0",
              "copyright": "",
              "focal_length": "0",
              "iso": "0",
              "shutter_speed": "0",
              "title": "",
              "orientation": "0",
              "keywords": [],
              "alt": ""
            }
          }
        ],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_wp_attached_file": [],
        "_wp_attachment_metadata": [],
        "_wp_attachment_image_alt": [],
        "_wp_attachment_backup_sizes": []
      },
      "terms": {}
    },
    {
      "id": 12033,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "1837"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "632251dc64cc9a66a3d5bb97804bffde2f6c497078be8c96a14ea1e4341697fd",
        "post_content": "834c81a8fbc773ad7a1d27f624ba3879f1ba918cb816da90017d0dd4ac95d755",
        "post_title": "7cc46422a9ed8a0719065d7b76a5091b55c2fcfd0218003afb1444ec448d6812",
        "post_excerpt": "c4c290ba3c403795398b2711266af33c74b8c6a7aee8f106845c030e186c1fe0",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "05e8fcf547f3fde858f41543f174ffcd8b072295cacfec4a61c90349f9cbb37c",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "2ca8df78708455287b21d6bc5ab3ed6c42e13628daeeede6bd9f3ee5b3cecdc3",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "d99d94427b7b92f4731b0afed974b7e7e8410292e802bd26d4ee8a3abc02187e"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_fuehrung": [
          "aec824a6c039a96dda94334871e91dbe69be712750ce7110638290d9b5541889"
        ],
        "_iss_editorial_enabled_fuehrung": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_fuehrung_skin": []
      },
      "identity": {
        "type": "fuehrung",
        "slug": "helden-der-arbeit-individuell-buchbar"
      },
      "format": "fuehrung",
      "document": {
        "schema_version": 1,
        "skin": "route-dossier",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p><strong>Helden der Arbeit</strong> ist eine biografische Zeitreise durch 120 Jahre Arbeitswelten in Oberschöneweide. Die Führung erzählt Industriegeschichte nicht abstrakt, sondern über Menschen, die an den jeweiligen Orten gearbeitet haben: von der ungelernten Arbeiterin im Kabelwerk über Angestellte und DDR-Beschäftigte bis hin zu heutigen High-Tech-Berufen.</p>",
            "media_refs": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "",
            "body": "<p>Zwischen Kaisersteg, Kabelwerk, HTW-Campus, Peter-Behrens-Bau und dem Weg bis zur Sensorikproduktion der Gegenwart folgen die Stationen jeweils einem konkreten Lebenslauf. Wie sah ein Arbeitstag aus? Was wurde verdient? Unter welchen Bedingungen wurde gearbeitet? Wo lebten die Menschen, was lasen sie in der Zeitung, welche Hoffnungen oder Zwänge prägten ihren Alltag?</p>\n\n<p>Gerade an der Geschichte des TRO werden dabei auch Konflikte und Brüche sichtbar: Lohnkämpfe und Streiks in den 1920er Jahren, Zwangsarbeit im Krieg, improvisierter Wiederaufbau nach 1945 und die Rolle des VEB TRO als Großbetrieb der DDR-Energiewirtschaft. Solche Erfahrungen rahmen die individuellen Biografien, die an den Stationen der Tour erzählt werden.</p>\n\n<p>Dadurch verbindet die Tour Industriekultur mit Sozialgeschichte. Sie zeigt nicht nur Fabrikarchitektur und Unternehmensgeschichte, sondern auch die Perspektive derjenigen, die den Standort getragen haben. Gerade in Oberschöneweide, wo sich Schwerindustrie, Verwaltung, Forschung und neue Produktion überlagern, werden die Veränderungen der Arbeitswelt besonders greifbar.</p>\n\n<p>Je nach Termin und Verfügbarkeit kann der Abschluss variieren. Im Mittelpunkt steht in jedem Fall die Frage, wie sich Arbeit von 1897 bis heute verändert hat und welche Spuren davon im Stadtraum noch immer sichtbar sind.</p>",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 12183,
      "new": false,
      "post": {
        "post_content": "<!-- wp:paragraph -->\r\n<p>Entdecken Sie das historische Gründerzentrum der Berliner Elektroindustrie – einen Ort, an dem industrielle Innovation Weltgeschichte schrieb und der heute erneut im Wandel steht. Bei diesem geführten Rundgang tauchen Sie ein in die Entwicklung von der „Elektropolis“ des frühen 20. Jahrhunderts hin zu einem vielseitigen Zukunftsraum für Wissenschaft, Produktion und Kultur.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Der Rundgang beginnt im Industriesalon mit einer kompakten Einführung: Historische Fotografien, originale Dokumente und fundierte Hintergrundinformationen eröffnen den Blick auf die Bedeutung Schöneweides als eines der wichtigsten Industriestandorte Europas. Von hier aus geht es direkt ins Gelände, wo sich die Geschichte im Stadtraum fortsetzt.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Entlang der monumentalen Industriefassaden erleben Sie die architektonischen Zeugnisse einer Epoche, in der hier Elektrotechnik in großem Maßstab entwickelt und produziert wurde. Gleichzeitig wird sichtbar, wie sich das Gebiet heute neu erfindet: Hinter den historischen Mauern verbergen sich überraschende Orte und neue Nutzungen. Dazu gehört die ehemalige AEG-Kantine ebenso wie Teile des einstigen Kabelwerks, die heute als moderner Campus der HTW Berlin genutzt werden.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Ein zentraler Strang der Tour ist das Transformatorenwerk Oberschöneweide (TRO), das 1921 auf dem ehemaligen Niles-Areal in Betrieb ging. Mit Hallen von Jean Krämer und Ernst Ziesel, Hochspannungslaboren, Prüffeldern und einer großmaßstäblichen Energieinfrastruktur entwickelte es sich zu einem der bedeutendsten Transformatorenwerke Deutschlands und belieferte Elektrizitätsgesellschaften weit über Berlin hinaus.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Der Rundgang folgt dabei auch den Brüchen des 20. Jahrhunderts: der Ausweitung der Produktion, Zwangsarbeit und Krieg, dem Wiederanfang nach 1945, der Rolle des VEB TRO als wichtiger Ausrüster der DDR-Energiewirtschaft und schließlich dem Strukturwandel nach 1990 bis zur Schließung 1996.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Der Rundgang verbindet Vergangenheit und Gegenwart und macht den tiefgreifenden Strukturwandel vor Ort unmittelbar erfahrbar – als lebendigen Prozess, der bis heute anhält.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p><strong>ACHTUNG: Der Behrensbau kann zur Zeit nicht besichtigt werden!!</strong></p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:iss/tour-calendar /-->\r\n\r\n<!-- wp:columns -->\r\n<div class=\"wp-block-columns\"><!-- wp:column {\"width\":\"100%\"} -->\r\n<div class=\"wp-block-column\" style=\"flex-basis: 100%;\"><!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group\"><!-- wp:columns -->\r\n<div class=\"wp-block-columns\"><!-- wp:column {\"width\":\"100%\"} -->\r\n<div class=\"wp-block-column\" style=\"flex-basis: 100%;\"> </div>\r\n<!-- /wp:column --></div>\r\n<!-- /wp:columns --></div>\r\n<!-- /wp:group --></div>\r\n<!-- /wp:column --></div>\r\n<!-- /wp:columns -->\r\n\r\n<!-- wp:iss/related-content {\"postType\":\"register_place\"} /-->"
      },
      "meta": {
        "_thumbnail_id": [
          "18840"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "8fe4f64d2bbb4aabc3bfa1008972e8907b4c21ab6db778cefb987c03308b1ef8",
        "post_content": "dd1be7ea5837ab42a63b51d6ae683576d66be0956b62998e9ee5b6a1fce0f0f2",
        "post_title": "9d0677bcdf45fbebab332ae4cbb93fa4c419a341a261a7188406028d24d55ec2",
        "post_excerpt": "438dda3f0400eeacacfcdd8dbf54e6dc06738ca3e37ec125e91871d42aa9817e",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "767562849c538313c6234dbe43564a0033fa5ce7d8aaaab0452d8786c33b5293",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "2ca8df78708455287b21d6bc5ab3ed6c42e13628daeeede6bd9f3ee5b3cecdc3",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "a1c742b864fdd92970bdea8006bc7a186f09a3887f6b9277d982a0c3a555c93e"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_fuehrung": [
          "3327f9e3db93a69d4f42b8d27e29258fc335a40088aeb635ae93fa7cc118446c"
        ],
        "_iss_editorial_enabled_fuehrung": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_fuehrung_skin": [
          "f7f23870a55b76da2d3b2ab5fd2f3b51c211f8537e11619b5b15a992d778b2cc"
        ]
      },
      "identity": {
        "type": "fuehrung",
        "slug": "elektropolis-tour"
      },
      "format": "fuehrung",
      "document": {
        "schema_version": 1,
        "skin": "route-dossier",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "bildbuehne",
            "kicker": "",
            "title": "Elektropolis",
            "body": "<p>Entdecken Sie das historische Gründerzentrum der Berliner <br>Elektroindustrie – einen Ort, an dem industrielle Innovation <br>Weltgeschichte schrieb und der heute erneut im Wandel steht. Bei diesem <br>geführten Rundgang tauchen Sie ein in die Entwicklung von der <br>„Elektropolis“ des frühen 20. Jahrhunderts hin zu einem vielseitigen <br>Zukunftsraum für Wissenschaft, Produktion und Kultur.</p>",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "18840",
                "label": "Schoeneweide Kraftwerk 2MP Sven Bock Schoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/04/Schoeneweide_Kraftwerk_2MP-300x236.jpg",
                "width": "1594",
                "height": "1254",
                "mime": "image/jpeg"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "18846",
                "label": "elektropolis-024",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/04/elektropolis-024-300x177.jpg",
                "width": "800",
                "height": "472",
                "mime": "image/jpeg"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "18847",
                "label": "elektropolis-020",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/04/elektropolis-020-300x224.jpg",
                "width": "640",
                "height": "477",
                "mime": "image/jpeg"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "18848",
                "label": "elektropolis-015",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/04/elektropolis-015-300x240.jpg",
                "width": "1603",
                "height": "1284",
                "mime": "image/jpeg"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "18849",
                "label": "elektropolis-osw1-009",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/04/elektropolis-osw1-009-300x278.jpg",
                "width": "388",
                "height": "360",
                "mime": "image/jpeg"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "18850",
                "label": "elektropolis-osw1-006",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/04/elektropolis-osw1-006-204x300.jpg",
                "width": "408",
                "height": "599",
                "mime": "image/jpeg"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "18851",
                "label": "elektropolis-osw1-001",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/04/elektropolis-osw1-001-300x252.jpg",
                "width": "1432",
                "height": "1204",
                "mime": "image/jpeg"
              }
            ]
          },
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p>Entdecken Sie das historische Gründerzentrum der Berliner Elektroindustrie – einen Ort, an dem industrielle Innovation Weltgeschichte schrieb und der heute erneut im Wandel steht. Bei diesem geführten Rundgang tauchen Sie ein in die Entwicklung von der „Elektropolis“ des frühen 20. Jahrhunderts hin zu einem vielseitigen Zukunftsraum für Wissenschaft, Produktion und Kultur.</p>",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26694",
                "label": "vladimir",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/event-drop-storage/accepted/projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f-300x169.png",
                "width": "1672",
                "height": "941",
                "mime": "image/png"
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Elektropolis",
            "body": "<p>Der Rundgang beginnt im Industriesalon mit einer kompakten Einführung: Historische Fotografien, originale Dokumente und fundierte Hintergrundinformationen eröffnen den Blick auf die Bedeutung Schöneweides als eines der wichtigsten Industriestandorte Europas. Von hier aus geht es direkt ins Gelände, wo sich die Geschichte im Stadtraum fortsetzt.</p>\n\n<p>Entlang der monumentalen Industriefassaden erleben Sie die architektonischen Zeugnisse einer Epoche, in der hier Elektrotechnik in großem Maßstab entwickelt und produziert wurde. Gleichzeitig wird sichtbar, wie sich das Gebiet heute neu erfindet: Hinter den historischen Mauern verbergen sich überraschende Orte und neue Nutzungen. Dazu gehört die ehemalige AEG-Kantine ebenso wie Teile des einstigen Kabelwerks, die heute als moderner Campus der HTW Berlin genutzt werden.</p>\n\n<p>Ein zentraler Strang der Tour ist das Transformatorenwerk Oberschöneweide (TRO), das 1921 auf dem ehemaligen Niles-Areal in Betrieb ging. Mit Hallen von Jean Krämer und Ernst Ziesel, Hochspannungslaboren, Prüffeldern und einer großmaßstäblichen Energieinfrastruktur entwickelte es sich zu einem der bedeutendsten Transformatorenwerke Deutschlands und belieferte Elektrizitätsgesellschaften weit über Berlin hinaus.</p>\n\n<p>Der Rundgang folgt dabei auch den Brüchen des 20. Jahrhunderts: der Ausweitung der Produktion, Zwangsarbeit und Krieg, dem Wiederanfang nach 1945, der Rolle des VEB TRO als wichtiger Ausrüster der DDR-Energiewirtschaft und schließlich dem Strukturwandel nach 1990 bis zur Schließung 1996.</p>\n\n<p>Der Rundgang verbindet Vergangenheit und Gegenwart und macht den tiefgreifenden Strukturwandel vor Ort unmittelbar erfahrbar – als lebendigen Prozess, der bis heute anhält.</p>\n\n<p><strong>ACHTUNG: Der Behrensbau kann zur Zeit nicht besichtigt werden!!</strong></p>\n\n<p> </p>",
            "media_refs": [],
            "media_layout": "inline"
          },
          {
            "type": "atlas_map",
            "kicker": "",
            "title": "",
            "body": "\n\n",
            "links": [],
            "treatment": "atlas-map.tour-route"
          },
          {
            "type": "upload_intake",
            "kicker": "Industriekultur durch deine Linse",
            "title": "Elektropolis im Kasten",
            "body": "Lade deine Bilder hoch und teile deine persönlichen Tour-Highlights mit uns. Nach einer kurzen redaktionellen Prüfung schalten wir dein Foto in unserer Galerie frei."
          },
          {
            "type": "material",
            "kicker": "",
            "title": "",
            "body": "",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26182",
                "label": "media",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/media-300x225.webp",
                "width": "1448",
                "height": "1086",
                "mime": "image/webp"
              }
            ],
            "links": []
          }
        ],
        "deleted_sections": [
          {
            "type": "galerie",
            "kicker": "",
            "title": "",
            "body": "",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26035",
                "label": "phantasy-iss",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/phantasy-iss-300x225.webp",
                "width": "1448",
                "height": "1086",
                "mime": "image/webp"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26036",
                "label": "jazz-in-iss",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/jazz-in-iss-1-300x225.webp",
                "width": "1448",
                "height": "1086",
                "mime": "image/webp"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26037",
                "label": "guitar",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/guitar-300x225.webp",
                "width": "1448",
                "height": "1086",
                "mime": "image/webp"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26034",
                "label": "veranstalltung-raum",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/veranstalltung-raum-300x200.webp",
                "width": "1536",
                "height": "1024",
                "mime": "image/webp"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26031",
                "label": "kwo-expert",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/kwo-expert-300x225.webp",
                "width": "1448",
                "height": "1086",
                "mime": "image/webp"
              }
            ],
            "gallery_layout": "wall",
            "deleted_at": "2026-07-05T16:55:04.061Z",
            "original_index": 5
          },
          {
            "type": "schluss",
            "kicker": "• Implemented.",
            "title": "non-destructiv",
            "body": "<p>it opens an add-confirmation modal, but it does not write a new gesture into the JSON. The section is created only when the editor clicks Zur<br>  Komposition hinzufügen. Drag/drop into the composition still inserts directly, so explicit structural placement remains fast.</p><p><br></p>",
            "links": [],
            "deleted_at": "2026-07-05T16:55:00.497Z",
            "original_index": 6
          }
        ]
      },
      "enabled": true
    },
    {
      "id": 12188,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "950"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "1509435f4c5663fefa7e99e06b93047dafccebd181b3a83e8c8a4145b2be0f10",
        "post_content": "4accfb864916291deefde902bbe694df3ff5d08e39fa3b254b97df819a42c85b",
        "post_title": "62d65e4a0d1c0cf6f7ff743f776670b3aae0ee3201dd899b567adfb8a404ed73",
        "post_excerpt": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "8e7e850f640a13429662ac2f04ed80864a42d57dbc66fe3f6811e081c2db8f08",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "2ca8df78708455287b21d6bc5ab3ed6c42e13628daeeede6bd9f3ee5b3cecdc3",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "4f146f11236845fe8036708a8da1095b95d9a2ae5bec3ba4867eede6f11f17fc"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_fuehrung": [
          "0d76c1b9da465f912688e9294f26275de73db61cd02560891c55e825e2ce41dd"
        ],
        "_iss_editorial_enabled_fuehrung": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_fuehrung_skin": []
      },
      "identity": {
        "type": "fuehrung",
        "slug": "mit-dem-fahrrad-die-waschewasch-tour"
      },
      "format": "fuehrung",
      "document": {
        "schema_version": 1,
        "skin": "route-dossier",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "bildbuehne",
            "kicker": "",
            "title": "",
            "body": "",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "950",
                "label": "R3_Waeschewasch-Tour",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2019/09/R3_Waeschewasch-Tour-300x202.jpg",
                "width": "560",
                "height": "377"
              }
            ]
          },
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p>Die Tour ist jederzeit buchbar. Sie kostet 100€ incl. 6 Teilnehmer, jeder weitere TN 15€, Kinder 8€. | ca. 3hVon Schöneweide bis Köpenick - vorbei an den ehemaligen Werks- und Wohnanlagen des Wäschereikönigs Wilhelm Spindler bis zum Reich der Waschfrauen-Königin, der berühmten Mutter Lustig in der Altstadt von Köpenick.</p>",
            "media_refs": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "",
            "body": "<p>Von Schöneweide bis Köpenick – immer an der Spree entlang gen Osten – vorbei an den ehemaligen Werks- und Wohnanlagen des Wäschereikönigs Wilhelm Spindler bis zum Reich der Waschfrauen-Königin, der berühmten Mutter Lustig in der Altstadt von Köpenick.</p>\n\n<p>Die geführte Fahrradtour ist jederzeit für Gruppen buchbar. Zu diesem Zweck bitten wir um eine vorherige Anmeldung!</p>\n\n<p><strong>Anmerkung:</strong><br><strong>Bitte kommen Sie mit dem eigenem Fahrrad!</strong></p>\n\n<p><strong>Ganzjährig</strong><br><strong><em>auf Anfrage</em></strong></p>\n\n<p><strong>Treffpunkt: </strong>Industriesalon/- oder frei zu vereinbarende Orte Ihrer Wahl<br><strong>Preis: </strong>100€ inkl. 6 TN, jeder weitere Teilnehmer 15€/Kinder 8€<br><strong>Dauer: </strong>ca. 3 Stunden</p>",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 12189,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "982"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "bee87b858f49371928b0251f7415dbfeb3f88777019f3413f3ee9d84b0515f73",
        "post_content": "ce4953415ba89e1e3ca565294276f36fcaefa1a5be85a573b3b2ea5c2a6e224f",
        "post_title": "9e2b348c7aed85a22d8e009aed1eb7067883074bc97a4124e51e154263326ca5",
        "post_excerpt": "1a91e1e5c029b36d388e7bdb9f5e0fee56b114aadfd01cfcce0988c3f5820d6a",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "dd7522895e8772f286518b41dc4788491991426d753a5ab4115c1f6b12a579e5",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "2ca8df78708455287b21d6bc5ab3ed6c42e13628daeeede6bd9f3ee5b3cecdc3",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "1c6bb1c2f40a102c24c0902669e2c4f44a02b5cefd1a7b0e149e94b9e30784f2"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_fuehrung": [
          "49ba83190ff545523116f0217b3c2103e97da6e33aa5c10e0ce55134657613cc"
        ],
        "_iss_editorial_enabled_fuehrung": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_fuehrung_skin": []
      },
      "identity": {
        "type": "fuehrung",
        "slug": "stadtrallye-fur-erwachsene"
      },
      "format": "fuehrung",
      "document": {
        "schema_version": 1,
        "skin": "route-dossier",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p><strong>Stromern auf der schönen Weide</strong> ist eine Stadtrallye für Erwachsene, Gruppen und Teams, die Oberschöneweide nicht im Vorbeigehen, sondern spielerisch und aufmerksam erkunden wollen. Ausgerüstet mit Aufgabenblatt und Lösungswort führt das Stadtspiel durch zentrale Orte der Berliner Elektroindustrie.</p>",
            "media_refs": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "",
            "body": "<p>Die Route beginnt am Peter-Behrens-Bau und folgt von dort den Spuren von AEG, NAG, Kabelwerk, Batteriefabrik und Kraftwerk. Unterwegs werden Industriearchitektur, technische Innovationen und Unternehmensgeschichte nicht frontal erklärt, sondern über Fragen, Beobachtungen und kleine Teamaufgaben erschlossen.</p>\n\n<p>An insgesamt 14 Stationen geht es um Details wie den Paternoster im Behrensbau, die Rathenau-Villa, die Batteriefabrik, die NAG- und KWO-Geschichte, das Kraftwerk Oberspree, das ehemalige Schalthaus, die Kranbahn und die DDR-Poliklinik in der Reinbeckstraße. Wer gut beobachtet, kombiniert und im Team arbeitet, kommt dem Lösungswort Schritt für Schritt näher.</p>\n\n<p>Die Rallye eignet sich besonders für Gruppen, Betriebsausflüge und Teambuilding-Formate. Zusatzaufgaben sorgen dafür, dass nicht nur Wissen, sondern auch Schätzvermögen, Kreativität und Zusammenarbeit gefragt sind. Zum Abschluss geht es zurück in den Industriesalon, wo die Auswertung und die kleine Siegerehrung stattfinden.</p>\n\n<p>So verbindet das Format Stadtgeschichte, Industriekultur und Gruppenaktivität zu einer eigenständigen Entdeckungstour durch das historische Oberschöneweide.</p>",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26196",
                "label": "Uli-Berger",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/Uli-Berger-300x240.webp",
                "width": "1402",
                "height": "1122"
              }
            ],
            "media_layout": "aside-right"
          },
          {
            "type": "atlas_map",
            "kicker": "",
            "title": "",
            "body": "",
            "links": [],
            "treatment": "atlas-map.tour-route"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 12190,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "981"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "1d20706702d4f9dd5085b9edcd8ab85340a35886e2eff8cda7c0a0951c437ecc",
        "post_content": "482b4996d97178d41ee218c0f6183adc88e7c469e1458970844919d96588ffe6",
        "post_title": "2e8659308d02d0a2103b1291bb84c6b34510aa3a80e2d7406102f51a44a4c244",
        "post_excerpt": "c10fb1cc0d4225a47710d7243a2e672ce16b8895471705ecc68acd22d36d1cbe",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "76244c3b67e58a05ac87bfd8bc79bf4a7a732295d5fb6cdec49dc1f42d6d6534",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "2ca8df78708455287b21d6bc5ab3ed6c42e13628daeeede6bd9f3ee5b3cecdc3",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "c436f0d7d4753726ebf1ca780a72fbfeb97af0a15b85f657ad088d10390759ff"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_fuehrung": [
          "fc73698bd90cefdf96b041ede635a5e78522112d2bdc42708ed8e23ecb93a68c"
        ],
        "_iss_editorial_enabled_fuehrung": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_fuehrung_skin": []
      },
      "identity": {
        "type": "fuehrung",
        "slug": "schulerrallye"
      },
      "format": "fuehrung",
      "document": {
        "schema_version": 1,
        "skin": "route-dossier",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p>Die <strong>Schülerrallye Hochspannung</strong> ist ein Stadtspiel für Schulklassen und Jugendgruppen im ehemaligen Transformatorenwerk Oberschöneweide. Nach einer kurzen Einführung im Industriesalon gehen die Teams mit Arbeitsmaterialien und Smartphones selbstständig auf Entdeckungstour durch eines der spannendsten Industrieensembles Berlins.</p>",
            "media_refs": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "",
            "body": "<p>Das Gelände war ab 1921 Standort des Transformatorenwerks Oberschöneweide (TRO), eines der wichtigsten deutschen Werke für Transformatoren und Hochspannungsschaltgeräte. Hallen, Prüffelder, Werkbahn und die benachbarte Energieinfrastruktur zeigen bis heute, wie eng Produktion, Logistik und Stromversorgung in Oberschöneweide zusammenhingen.</p>\n\n<p>Die Rallye führt in mehreren Etappen vom Peter-Behrens-Bau über Rathenau-Villa, Batteriefabrik, HTW-Campus und Kabelwerk bis zu Kraftwerk, Schalthaus, Kranbahn und ehemaliger Poliklinik. An den Stationen müssen Fragen gelöst, Hinweise kombiniert und ein Lösungswort entschlüsselt werden. Extra-Aufgaben bringen Zusatzpunkte und fördern genaues Hinsehen, Wissen und Teamarbeit.</p>\n\n<p>Im Unterschied zu einer klassischen Führung bleibt die Gruppe in Bewegung und erschließt sich das Gelände aktiv. Technikgeschichte, Architektur und Alltagsgeschichte werden dabei spielerisch miteinander verbunden. Die Rallye ist besonders geeignet für Jugendliche zwischen 11 und 15 Jahren und lässt sich gut in Projekttage oder Exkursionen einbinden.</p>\n\n<p>Am Ende geht es zurück in den Industriesalon, wo die Lösungen ausgewertet werden und die Gruppe den gemeinsamen Rundgang abschließen kann.</p>",
            "media_refs": [],
            "media_layout": "inline"
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Aufsichtspflicht",
            "body": "<p>Die gesetzliche Aufsichtspflicht über die teilnehmenden Schülerinnen und Schüler bleibt bei den begleitenden Lehrkräften und wird nicht von den Guides des Industriesalon übernommen.</p>",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 12191,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "1354"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "55b8d7aa3f2fdda7699450cb359f154f83ebf5e050776e6b59ac00179de30dcc",
        "post_content": "da6fdf7d46b856f1f0cf15cad9f85ba59cfad9987d0ffcf670aa84259c873d54",
        "post_title": "05918b3186b6c5dd3b4eeed27dde0ecdd140b25aa1f202bb0750874f49dd446c",
        "post_excerpt": "7313b8fac67df862761da2a6101ec649342009bd50f76257749e0dd9c6e109c9",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "53dc396f3bcb4a356ec409cc02b613f0a839cb9cbc785bfd887febdcd5080313",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "2ca8df78708455287b21d6bc5ab3ed6c42e13628daeeede6bd9f3ee5b3cecdc3",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "0eac3cab6bc0de095d4c50d2fd192140df7562baf382426da77b8aef291a480d"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_fuehrung": [
          "d97c3314110f8938f4ef3675571ce355fb9177c342db131877c822587b6447dd"
        ],
        "_iss_editorial_enabled_fuehrung": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_fuehrung_skin": []
      },
      "identity": {
        "type": "fuehrung",
        "slug": "familienrallye"
      },
      "format": "fuehrung",
      "document": {
        "schema_version": 1,
        "skin": "compact",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "bildbuehne",
            "kicker": "",
            "title": "",
            "body": "",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "1354",
                "label": "Bilderrally_website",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2020/01/Bilderrally_website-300x211.jpg",
                "width": "992",
                "height": "699",
                "mime": "image/jpeg"
              }
            ]
          },
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p>Die <strong>Familienrallye: Finde den Unterschied!</strong> lädt dazu ein, Oberschöneweide mit historischen Fotografien neu zu entdecken. Im Industriesalon erhalten Sie eine Mappe mit zwölf Aufnahmen, die Gebäude entlang der Wilhelminenhofstraße zwischen Edison- und Ostendstraße zeigen.</p>",
            "media_refs": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "",
            "body": "<p>Die Reihenfolge der Bilder ist frei wählbar. Ihre Aufgabe ist es, die jeweiligen Standorte zu finden, die historischen Ansichten mit der Gegenwart zu vergleichen und die Fragen auf dem Antwortbogen zu lösen. Gerade weil viele Gebäude über mehr als 100 Jahre erstaunlich gut erhalten geblieben sind, lohnt sich der genaue Blick auf Fassaden, Fenster, Dächer und kleine bauliche Veränderungen.</p>\n\n<p>Die Motive führen unter anderem zur AEG-Montagehalle, zum Kasino, zum Kabelwerk, zum Arbeiterwohlfahrthaus, zum Peter-Behrens-Bau, zur Akkumulatorenfabrik, zum Kraftwerk, zum Kaisersteg und wieder zurück zum Industriesalon. So verbindet die Rallye spielerische Spurensuche mit Architektur- und Industriegeschichte.</p>\n\n<p>Wer alle Antworten gefunden hat, bringt den Bogen zurück in den Industriesalon. Dort wird ausgewertet und bei richtig gelösten Aufgaben wartet eine kleine Überraschung. Die Rallye funktioniert im eigenen Tempo und eignet sich für Einzelpersonen, Familien und kleine Gruppen gleichermaßen.</p>\n\n<p><strong>Hinweis:</strong> Die Unterlagen können während der regulären Öffnungszeiten am Counter abgeholt werden. Für internationale Familien oder Gruppen liegt zudem eine englische Einführung vor.</p>",
            "media_refs": [],
            "media_layout": "inline"
          },
          {
            "type": "atlas_map",
            "kicker": "vorspann",
            "title": "",
            "body": "",
            "links": [],
            "treatment": "atlas-map.tour-route"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 12257,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "b778b5fc429aa56c7c814846a25aeea4b345da1de512dd0f56fbcbffc33b240a",
        "post_content": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_title": "4ea140588150773ce3aace786aeef7f4049ce100fa649c94fbbddb960f1da942",
        "post_excerpt": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "3433ce4458374b4b1a00fdcee66a2df8e4431103f3a2544204a6f8e855d8e6f3",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3660315a9af3df255d8f19ab077e4797822b41488a0e2a04bc6af71213c23274",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_landing": [
          "23b5e862173c0de8bfaaa90e3c7b029a737ac7739f7af21e7cc50e06ddb3591f"
        ],
        "_iss_editorial_enabled_landing": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_landing_skin": [
          "8730f957b1e7fff729c85ec84f3f9b38fe6a5be2c4c7a4faed30091dc9ef04b2"
        ]
      },
      "identity": {
        "type": "page",
        "slug": "home-2"
      },
      "format": "landing",
      "document": {
        "schema_version": 3,
        "skin": "frontpage",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "feature",
            "kicker": "forum für",
            "title": "Industriekultur und Transformation",
            "body": "<p>Entdecken Sie <strong>Schöneweide</strong>: Industriegeschichte, Stadtraum und Transformation.</p>",
            "lead": "",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26778",
                "label": "Außenansicht des Industriesalon Schöneweide",
                "thumbnail": ""
              }
            ],
            "links": [
              {
                "label": "Führung buchen",
                "url": "{{SITE_URL}}/fuehrungen/",
                "page_id": "13301"
              },
              {
                "label": "Raum anfragen",
                "url": "{{SITE_URL}}/salon-vermietung/",
                "page_id": "12606"
              }
            ],
            "facts": [],
            "treatment": "feature.opening",
            "media_layout": "50-50"
          },
          {
            "type": "statement",
            "kicker": "",
            "title": "Schöneweide ist eines der größten industriell geprägten Denkmal-Ensembles in Deutschland.",
            "body": "Der Industriesalon führt Gäste aus aller Welt durch die herausragenden Zeugnisse historischer Industriearchitektur, die in Schöneweide in seltener Vielfalt erhalten sind. Die angebotenen Touren geben vielfältige Einblicke in die Entwicklung zu einem Berliner Zukunftsort - eine spannende Mischung aus nachhaltigem Städtebau, Wissenschaft, Kunst und Produktion.",
            "links": [
              {
                "label": "Schöneweide entdecken",
                "url": "{{SITE_URL}}/schoneweide/",
                "page_id": "13251"
              }
            ],
            "treatment": "statement.lead"
          },
          {
            "type": "gateway",
            "kicker": "Vor Ort",
            "title": "Den Industriesalon entdecken",
            "body": "Der Industriesalon lässt sich vor Ort als Rundgang, Ausstellung und öffentlicher Termin erleben. Die kulturellen Zugänge stehen hier vor der Serviceebene.",
            "anchor": "vor-ort",
            "items": [
              {
                "label": "Führungen",
                "text": "Entdecken Sie Industriegeschichte, Stadtraum und Transformation in unseren öffentlichen und buchbaren Rundgängen.",
                "url": "{{SITE_URL}}/fuehrungen/",
                "media_refs": [
                  {
                    "kind": "media",
                    "source": "wp-media",
                    "id": "26050",
                    "label": "2024-09-05-Sven-Bock-Touren-Industriesalon-Vermittlung-Industriekultur-45",
                    "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/2024-09-05-Sven-Bock-Touren-Industriesalon-Vermittlung-Industriekultur-45-300x225.webp",
                    "width": "2560",
                    "height": "1920"
                  }
                ],
                "page_id": "13301"
              },
              {
                "label": "Ausstellungen",
                "text": "Objekte, Bilder und Dokumente zeigen Industriegeschichte im Zusammenhang von Ort und Produktion.",
                "url": "{{SITE_URL}}/ausstellungen/",
                "media_refs": [
                  {
                    "kind": "media",
                    "source": "wp-media",
                    "id": "26047",
                    "label": "j.cizeikaite_industriesalon_03_400dpi",
                    "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/j.cizeikaite_industriesalon_03_400dpi-300x200.webp",
                    "width": "2362",
                    "height": "1575"
                  }
                ],
                "page_id": "13255"
              },
              {
                "label": "Veranstaltungen",
                "text": "Vorträge, Gespräche und kleinere Formate bringen Gegenwart und Industriekultur zusammen.",
                "url": "{{SITE_URL}}/veranstaltungen/",
                "media_refs": [
                  {
                    "kind": "media",
                    "source": "wp-media",
                    "id": "26048",
                    "label": "Im Industriesalon_Vortrag",
                    "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/Im-Industriesalon_Vortrag-300x200.webp",
                    "width": "1417",
                    "height": "945"
                  }
                ],
                "page_id": "13300"
              }
            ],
            "treatment": "gateway.cards"
          },
          {
            "type": "dynamic_slot",
            "kicker": "",
            "title": "",
            "body": "",
            "anchor": "projekte",
            "slot_key": "front-projects",
            "treatment": "slot.projects"
          },
          {
            "type": "dynamic_slot",
            "kicker": "",
            "title": "Demnächst",
            "body": "",
            "anchor": "gegenwart",
            "slot_key": "front-timeline",
            "treatment": "slot.timeline"
          },
          {
            "type": "feature",
            "kicker": "Raum nutzen",
            "title": "Salon mieten",
            "body": "Veranstaltungen, Workshops und Empfänge in einer ehemaligen Produktionshalle, mitten im historischen Industriegebiet von Schöneweide.\n\nStühle, Tische, WLAN, Tonanlage, Beamer und Leinwand können nach Bedarf genutzt werden.",
            "lead": "",
            "anchor": "raum-nutzen",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13313",
                "label": "Salonvermietung im Industriesalon",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/rental1-300x204.png",
                "width": "1522",
                "height": "1033"
              }
            ],
            "links": [
              {
                "label": "Details und Anfrage",
                "url": "{{SITE_URL}}/salon-vermietung/",
                "page_id": "12606"
              }
            ],
            "facts": [
              {
                "value": "650 m²",
                "label": "Halle mit Ausstellung und Galerie"
              },
              {
                "value": "350 m²",
                "label": "Ausstellungsraum, 7 m Höhe"
              },
              {
                "value": "100 m²",
                "label": "Veranstaltungsraum mit Bühne und Terrasse"
              }
            ],
            "treatment": "feature.media-panel",
            "media_layout": "50-50"
          },
          {
            "type": "gateway",
            "kicker": "Archiv &amp; Wissen",
            "title": "Quellen, Erinnerung und Entwicklung",
            "body": "Archiv, Sammlungen, Publikationen und Videos machen die historischen Schichten Schöneweides sichtbar. Sie dokumentieren industrielle Entwicklungen, gesellschaftliche Veränderungen und die Menschen, die den Standort geprägt haben.",
            "anchor": "archiv-wissen",
            "items": [
              {
                "label": "Sammlungen",
                "text": "",
                "url": "/sammlungen/",
                "media_refs": [
                  {
                    "kind": "media",
                    "source": "wp-media",
                    "id": "13327",
                    "label": "archiv1",
                    "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/archiv1-300x225.png",
                    "width": "1448",
                    "height": "1086"
                  }
                ]
              },
              {
                "label": "Publikationen",
                "text": "",
                "url": "/publikationen/",
                "media_refs": [
                  {
                    "kind": "media",
                    "source": "wp-media",
                    "id": "12426",
                    "label": "715yWXXfaFL._SL1500_",
                    "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/04/715yWXXfaFL._SL1500_-300x286.jpg",
                    "width": "1500",
                    "height": "1431"
                  }
                ]
              },
              {
                "label": "Archiv",
                "text": "",
                "url": "/archiv/",
                "media_refs": [
                  {
                    "kind": "media",
                    "source": "wp-media",
                    "id": "5348",
                    "label": "emil_rathenau",
                    "thumbnail": "{{SITE_URL}}/wp-content/uploads/2022/05/emil_rathenau-216x300.jpg",
                    "width": "1600",
                    "height": "2224"
                  }
                ]
              },
              {
                "label": "Videos",
                "text": "",
                "url": "/videos/",
                "media_refs": [
                  {
                    "kind": "media",
                    "source": "wp-media",
                    "id": "26041",
                    "label": "video",
                    "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/video-300x169.webp",
                    "width": "1672",
                    "height": "941"
                  }
                ]
              }
            ],
            "treatment": "gateway.cards"
          },
          {
            "type": "feature",
            "kicker": "Industriesalon",
            "title": "Über uns",
            "body": "Der Industriesalon Schöneweide ist Museum, Lernort und Ausgangspunkt, um den Stadtteil über seine Industriegeschichte, seine Architektur und seine heutigen Veränderungen zu verstehen.",
            "lead": "",
            "anchor": "industriesalon",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "180",
                "label": "Industriesalon Schöneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2019/02/2030-20171001-boxenstopp-3483-300x200.jpg",
                "width": "1600",
                "height": "1067"
              }
            ],
            "links": [
              {
                "label": "Weiter",
                "url": "{{SITE_URL}}/about/",
                "page_id": "13123"
              }
            ],
            "facts": [
              {
                "value": "Arbeitsweise",
                "label": "Mitglieder werden über Aktivitäten und Entwicklungen auf dem Laufenden gehalten. Gleichzeitig bleibt Raum für Begegnung, Feiern, Gespräche und die gemeinsame Arbeit an einem besonderen Ort."
              },
              {
                "value": "Mitarbeit",
                "label": "Der Industriesalon lebt nicht nur von Beständen, sondern von Menschen, die führen, ordnen, recherchieren, aufbauen, zuhören oder einfach mit anpacken."
              },
              {
                "value": "Mitglied werden",
                "label": "Wer den Industriesalon unterstützen möchte, kann Mitglied werden, mitarbeiten oder spenden. Die Formen sind verschieden, aber sie sichern denselben Ort."
              },
              {
                "value": "Netzwerk im Stadtteil",
                "label": "Der Industriesalon arbeitet mit Nachbarschaft, Initiativen, Schulen, Kulturpartnern und lokalen Akteuren zusammen. Dadurch entsteht ein Ort, der nicht nur bewahrt, sondern Austausch ermöglicht und Schöneweide als gemeinsamen Erinnerungs- und Erfahrungsraum stärkt."
              }
            ],
            "treatment": "feature.image-overlay",
            "media_layout": "50-50"
          },
          {
            "type": "dynamic_slot",
            "kicker": "",
            "title": "",
            "body": "",
            "anchor": "besuch",
            "slot_key": "front-visit-info",
            "treatment": "slot.visit-info"
          },
          {
            "type": "dynamic_slot",
            "kicker": "",
            "title": "",
            "body": "",
            "slot_key": "front-newsletter",
            "treatment": "slot.newsletter"
          }
        ],
        "deleted_sections": [
          {
            "type": "atlas_map",
            "kicker": "",
            "title": "",
            "body": "",
            "links": [],
            "treatment": "atlas-map.place-locator",
            "deleted_at": "2026-09-21T16:01:27.733Z",
            "original_index": 10
          },
          {
            "type": "text_bild_reihe",
            "kicker": "Archiv und Wissen",
            "title": "Geschichten entdecken",
            "body": "",
            "items": [
              {
                "label": "Sammlungen",
                "text": "Archiv, Sammlungen, Publikationen und Videos machen die historischen Schichten Schöneweides sichtbar. <a href=\"/sammlungen/\"><span class=\"iss-ink-e81d25\">Sammlungen entdecken</span></a>",
                "media_refs": [
                  {
                    "kind": "media",
                    "source": "wp-media",
                    "id": "13327",
                    "label": "archiv1",
                    "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/archiv1-300x225.png",
                    "width": "1448",
                    "height": "1086"
                  }
                ]
              }
            ],
            "treatment": "text-bild-reihe.visual",
            "deleted_at": "2026-09-21T16:01:16.921Z",
            "original_index": 7
          },
          {
            "type": "feature",
            "kicker": "vorspann t",
            "title": "titel",
            "body": "Der Industriesalon führt Gäste aus aller Welt durch die herausragenden Zeugnisse historischer Industriearchitektur, die in Schöneweide in seltener Vielfalt erhalten sind. Die angebotenen Touren geben vielfältige Einblicke in die Entwicklung zu einem Berliner Zukunftsort – eine spannende Mischung aus nachhaltigem Städtebau, Wissenschaft, Kunst und Produktion.",
            "lead": "",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26182",
                "label": "media",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/media-300x225.webp",
                "width": "1448",
                "height": "1086"
              }
            ],
            "links": [],
            "facts": [
              {
                "value": "qw",
                "label": "Der Industriesalon führt Gäste aus aller Welt durch die herausragenden Zeugnisse historischer Industriearchitektur, die in Schöneweide in seltener Vielfalt erhalten sind. Die angebotenen Touren geben vielfältige Einblicke in die Entwicklung zu einem Berliner Zukunftsort – eine spannende Mischung aus nachhaltigem Städtebau, Wissenschaft, Kunst und Produktion."
              }
            ],
            "treatment": "feature.media-panel",
            "media_layout": "50-50",
            "deleted_at": "2026-07-01T11:46:03.275Z",
            "original_index": 9
          },
          {
            "type": "feature",
            "kicker": "",
            "title": "",
            "body": "Der Industriesalon führt Gäste aus aller Welt durch die herausragenden Zeugnisse historischer Industriearchitektur, die in Schöneweide in seltener Vielfalt erhalten sind. Die angebotenen Touren geben vielfältige Einblicke in die Entwicklung zu einem Berliner Zukunftsort – eine spannende Mischung aus nachhaltigem Städtebau, Wissenschaft, Kunst und Produktion.",
            "lead": "",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26196",
                "label": "Uli-Berger",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/Uli-Berger-300x240.webp",
                "width": "1402",
                "height": "1122"
              }
            ],
            "links": [
              {
                "label": "videos",
                "url": "{{SITE_URL}}/videos/",
                "page_id": "13359"
              }
            ],
            "facts": [],
            "treatment": "feature.media-panel",
            "media_layout": "50-50",
            "deleted_at": "2026-07-01T10:12:43.484Z",
            "original_index": 9
          }
        ]
      },
      "enabled": true
    },
    {
      "id": 12606,
      "new": false,
      "post": {
        "post_content": "<!-- wp:group {\"tagName\":\"main\",\"className\":\"iss-rental-page\",\"layout\":{\"type\":\"default\"}} -->\n<main class=\"wp-block-group iss-rental-page\"><!-- wp:cover {\"url\":\"/wp-content/uploads/2026/05/rental1-1024x695.png\",\"id\":13313,\"dimRatio\":55,\"customOverlayColor\":\"#996032\",\"isUserOverlayColor\":false,\"focalPoint\":{\"x\":0.6,\"y\":0.54},\"minHeight\":68,\"minHeightUnit\":\"vh\",\"sizeSlug\":\"large\",\"align\":\"full\",\"className\":\"iss-rental-hero\"} -->\n<div class=\"wp-block-cover alignfull iss-rental-hero\" style=\"min-height:68vh\"><img class=\"wp-block-cover__image-background wp-image-13313 size-large\" alt=\"\" src=\"/wp-content/uploads/2026/05/rental1-1024x695.png\" style=\"object-position:60% 54%\" data-object-fit=\"cover\" data-object-position=\"60% 54%\"/><span aria-hidden=\"true\" class=\"wp-block-cover__background has-background-dim-60 has-background-dim\" style=\"background-color:#996032\"></span><div class=\"wp-block-cover__inner-container\"><!-- wp:group {\"className\":\"iss-container iss-rental-hero__inner\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-container iss-rental-hero__inner\"><!-- wp:group {\"className\":\"iss-heading iss-heading\\u002d\\u002duncaged\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-heading iss-heading--uncaged\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dlight iss-kicker\\u002d\\u002dlg\"} -->\n<p class=\"iss-kicker iss-kicker--light iss-kicker--lg\">Raum für</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":1,\"className\":\"iss-rental-hero__title\"} -->\n<h1 class=\"wp-block-heading iss-rental-hero__title\">Anlässe und Begegnungen</h1>\n<!-- /wp:heading -->\n\n<!-- wp:buttons {\"className\":\"iss-rental-hero__actions\"} -->\n<div class=\"wp-block-buttons iss-rental-hero__actions\"><!-- wp:button {\"className\":\"is-style-fill\"} -->\n<div class=\"wp-block-button is-style-fill\"><a class=\"wp-block-button__link wp-element-button\" href=\"#anfrage\">Anfrage stellen</a></div>\n<!-- /wp:button -->\n\n<!-- wp:button {\"backgroundColor\":\"vaporous-white\",\"textColor\":\"eerie-black\",\"className\":\"is-style-outline iss-rental-hero__secondary\"} -->\n<div class=\"wp-block-button is-style-outline iss-rental-hero__secondary\"><a class=\"wp-block-button__link has-eerie-black-color has-vaporous-white-background-color has-text-color has-background wp-element-button\" href=\"#moeglichkeiten\">Möglichkeiten ansehen</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></div></div>\n<!-- /wp:cover -->\n\n<!-- wp:group {\"tagName\":\"section\",\"className\":\"section iss-rental-intro\",\"layout\":{\"type\":\"constrained\"}} -->\n<section class=\"wp-block-group section iss-rental-intro\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:columns {\"verticalAlignment\":\"top\",\"className\":\"iss-rental-intro__grid\"} -->\n<div class=\"wp-block-columns are-vertically-aligned-top iss-rental-intro__grid\"><!-- wp:column {\"verticalAlignment\":\"top\",\"width\":\"60%\"} -->\n<div class=\"wp-block-column is-vertically-aligned-top\" style=\"flex-basis:60%\"><!-- wp:group {\"className\":\"iss-heading\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-heading\"><!-- wp:paragraph {\"className\":\"iss-kicker\"} -->\n<p class=\"iss-kicker\">Raum mit Charakter</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"className\":\"iss-heading__title\"} -->\n<h2 class=\"wp-block-heading iss-heading__title\">Für Formate, die mehr brauchen als einen neutralen Mietraum.</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-heading__text\"} -->\n<p class=\"iss-heading__text\">Wer im Industriesalon mietet, bekommt keinen anonymen Veranstaltungsraum. Der Ort bringt Geschichte, Materialität und Atmosphäre bereits mit. Das macht ihn besonders passend für Veranstaltungen, die in Erinnerung bleiben sollen und einen glaubwürdigen Rahmen brauchen.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Ob kleiner Empfang, interner Workshop, Pressegespräch, Lesung, Gesprächsabend, Ausstellungseröffnung oder ein sorgfältig geplanter privater Anlass: Der Salon bietet einen ruhigen, markanten Rahmen direkt in Oberschöneweide. Je nach Anlass lässt sich die Nutzung sachlich, kulturell oder gastlich anlegen.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"verticalAlignment\":\"top\",\"width\":\"40%\"} -->\n<div class=\"wp-block-column is-vertically-aligned-top\" style=\"flex-basis:40%\"><!-- wp:group {\"className\":\"iss-rental-facts\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-rental-facts\"><!-- wp:group {\"className\":\"iss-card iss-card\\u002d\\u002dinfo iss-card\\u002d\\u002dflat iss-card\\u002d\\u002dbrown\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card iss-card--info iss-card--flat iss-card--brown\"><!-- wp:group {\"className\":\"iss-card__body\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card__body\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Auf einen Blick</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3,\"className\":\"iss-card__title\"} -->\n<h3 class=\"wp-block-heading iss-card__title\">Gut geeignet für</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-card__text\"} -->\n<p class=\"iss-card__text\">Empfänge, Vorträge, kleinere Tagungen, Vereinsabende, Kulturformate, Präsentationen, Gesprächsrunden und ausgewählte Feiern.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-card iss-card\\u002d\\u002dinfo iss-card\\u002d\\u002dflat iss-card\\u002d\\u002dblue\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card iss-card--info iss-card--flat iss-card--blue\"><!-- wp:group {\"className\":\"iss-card__body\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card__body\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Lage</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3,\"className\":\"iss-card__title\"} -->\n<h3 class=\"wp-block-heading iss-card__title\">Mitten in Schöneweide</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-card__text\"} -->\n<p class=\"iss-card__text\">Ein besonderer Ort am historischen Industriestandort mit eigener Geschichte und guter Anbindung im Berliner Südosten.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-card iss-card\\u002d\\u002dinfo iss-card\\u002d\\u002dflat iss-card\\u002d\\u002dgreen\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card iss-card--info iss-card--flat iss-card--green\"><!-- wp:group {\"className\":\"iss-card__body\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card__body\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Anfrage</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3,\"className\":\"iss-card__title\"} -->\n<h3 class=\"wp-block-heading iss-card__title\">Individuell abgestimmt</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-card__text\"} -->\n<p class=\"iss-card__text\">Nutzung, Umfang, Zeiten und Begleitprogramm werden passend zum Anlass besprochen. So bleibt der Rahmen stimmig und realistisch planbar.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group -->\n\n<!-- wp:group {\"tagName\":\"section\",\"className\":\"section section\\u002d\\u002dalt section\\u002d\\u002dwith-rail iss-rental-uses\",\"layout\":{\"type\":\"constrained\"}} -->\n<section id=\"moeglichkeiten\" class=\"wp-block-group section section--alt section--with-rail iss-rental-uses\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:group {\"className\":\"iss-heading\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-heading\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dbrown\"} -->\n<p class=\"iss-kicker iss-kicker--brown\">Möglichkeiten</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"className\":\"iss-heading__title\"} -->\n<h2 class=\"wp-block-heading iss-heading__title\">Typische Nutzungen des Salons.</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-heading__text\"} -->\n<p class=\"iss-heading__text\">Nicht jeder Anlass braucht dieselbe Dramaturgie. Deshalb ist der Salon nicht als starres Paket gedacht, sondern als Raum, der auf unterschiedliche Formate zugeschnitten werden kann.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-card-grid iss-rental-uses__grid\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-card-grid iss-rental-uses__grid\"><!-- wp:group {\"className\":\"iss-card iss-rental-usage-card iss-card\\u002d\\u002dbrown\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card iss-rental-usage-card iss-card--brown\"><!-- wp:image {\"sizeSlug\":\"full\",\"linkDestination\":\"none\",\"className\":\"iss-card__media\"} -->\n<figure class=\"wp-block-image size-full iss-card__media\"><img src=\"/wp-content/uploads/2026/04/salon-empfang.jpg\" alt=\"Empfang im Salon\"/></figure>\n<!-- /wp:image -->\n\n<!-- wp:group {\"className\":\"iss-card__body\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card__body\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact iss-kicker\\u002d\\u002dbrown\"} -->\n<p class=\"iss-kicker iss-kicker--compact iss-kicker--brown\">Empfang</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3,\"className\":\"iss-card__title\"} -->\n<h3 class=\"wp-block-heading iss-card__title\">Abende, Treffen, kleine Feiern</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-card__text\"} -->\n<p class=\"iss-card__text\">Für Anlässe mit persönlicher Atmosphäre: Empfang, Jubiläum, kleiner Festabend oder ein bewusst gesetztes Zusammenkommen in besonderem Rahmen.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-card iss-rental-usage-card iss-card\\u002d\\u002dblue\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card iss-rental-usage-card iss-card--blue\"><!-- wp:image {\"sizeSlug\":\"full\",\"linkDestination\":\"none\",\"className\":\"iss-card__media\"} -->\n<figure class=\"wp-block-image size-full iss-card__media\"><img src=\"/wp-content/uploads/2026/04/salon-vortrag.jpg\" alt=\"Vortrag oder Diskussion\"/></figure>\n<!-- /wp:image -->\n\n<!-- wp:group {\"className\":\"iss-card__body\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card__body\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact iss-kicker\\u002d\\u002dblue\"} -->\n<p class=\"iss-kicker iss-kicker--compact iss-kicker--blue\">Diskurs</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3,\"className\":\"iss-card__title\"} -->\n<h3 class=\"wp-block-heading iss-card__title\">Vorträge, Panels, Gespräche</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-card__text\"} -->\n<p class=\"iss-card__text\">Für Themen, die einen eigenständigen Ort brauchen: Lesung, Podium, Gesprächsabend, Presseformat, Präsentation oder moderierter Austausch.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-card iss-rental-usage-card iss-card\\u002d\\u002dgreen\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card iss-rental-usage-card iss-card--green\"><!-- wp:image {\"sizeSlug\":\"full\",\"linkDestination\":\"none\",\"className\":\"iss-card__media\"} -->\n<figure class=\"wp-block-image size-full iss-card__media\"><img src=\"/wp-content/uploads/2026/04/salon-workshop.jpg\" alt=\"Workshop im Salon\"/></figure>\n<!-- /wp:image -->\n\n<!-- wp:group {\"className\":\"iss-card__body\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-card__body\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact iss-kicker\\u002d\\u002dgreen\"} -->\n<p class=\"iss-kicker iss-kicker--compact iss-kicker--green\">Arbeit</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3,\"className\":\"iss-card__title\"} -->\n<h3 class=\"wp-block-heading iss-card__title\">Workshops, Klausuren, interne Formate</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-card__text\"} -->\n<p class=\"iss-card__text\">Für Teams, Initiativen und Organisationen, die abseits standardisierter Seminarhotels zusammenkommen möchten.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group -->\n\n<!-- wp:group {\"tagName\":\"section\",\"className\":\"section iss-rental-qualities\",\"layout\":{\"type\":\"constrained\"}} -->\n<section class=\"wp-block-group section iss-rental-qualities\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:columns {\"verticalAlignment\":\"top\",\"className\":\"iss-rental-qualities__grid\"} -->\n<div class=\"wp-block-columns are-vertically-aligned-top iss-rental-qualities__grid\"><!-- wp:column {\"verticalAlignment\":\"top\",\"width\":\"42%\"} -->\n<div class=\"wp-block-column is-vertically-aligned-top\" style=\"flex-basis:42%\"><!-- wp:image {\"sizeSlug\":\"large\",\"className\":\"iss-rental-qualities__image\"} -->\n<figure class=\"wp-block-image size-large iss-rental-qualities__image\"><img src=\"/wp-content/uploads/2026/04/salon-detail.jpg\" alt=\"Detailansicht des Salons\"/></figure>\n<!-- /wp:image --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"verticalAlignment\":\"top\",\"width\":\"58%\"} -->\n<div class=\"wp-block-column is-vertically-aligned-top\" style=\"flex-basis:58%\"><!-- wp:group {\"className\":\"iss-heading\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-heading\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dblue\"} -->\n<p class=\"iss-kicker iss-kicker--blue\">Warum hier</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"className\":\"iss-heading__title\"} -->\n<h2 class=\"wp-block-heading iss-heading__title\">Ein Ort, der einer Veranstaltung sofort Profil gibt.</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-heading__text\"} -->\n<p class=\"iss-heading__text\">Viele Räume funktionieren technisch, bleiben aber austauschbar. Der Salon ist anders: Er hat Präsenz, erzählt etwas über den Standort und setzt einen deutlichen Ton, ohne laut zu werden.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:list {\"className\":\"iss-rental-list\"} -->\n<ul class=\"wp-block-list iss-rental-list\"><!-- wp:list-item -->\n<li>historischer Kontext statt neutraler Eventkulisse</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>geeignet für kulturelle, institutionelle und nachbarschaftliche Formate</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>glaubwürdiger Rahmen für Inhalte mit Bezug zu Stadt, Industrie, Geschichte oder Gemeinschaft</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>persönlich, konzentriert und kleiner als klassische Veranstaltungshäuser</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>auf Wunsch kombinierbar mit Führung oder thematischem Bezug zum Haus</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p>Gerade für Organisationen, Initiativen, Unternehmen, Stiftungen oder Privatpersonen, die einen besonderen Berliner Ort suchen, kann das den Unterschied machen. Der Raum ist nicht bloß Hintergrund, sondern Teil des Eindrucks.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group -->\n\n<!-- wp:group {\"tagName\":\"section\",\"className\":\"section section\\u002d\\u002dalt iss-rental-process\",\"layout\":{\"type\":\"constrained\"}} -->\n<section class=\"wp-block-group section section--alt iss-rental-process\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:group {\"className\":\"iss-heading\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-heading\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dgreen\"} -->\n<p class=\"iss-kicker iss-kicker--green\">Ablauf</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"className\":\"iss-heading__title\"} -->\n<h2 class=\"wp-block-heading iss-heading__title\">So läuft eine Anfrage ab.</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-heading__text\"} -->\n<p class=\"iss-heading__text\">Damit Aufwand und Erwartungen zusammenpassen, klären wir Anlass, Umfang und gewünschte Nutzung vorab. So lässt sich früh einschätzen, ob der Salon der richtige Ort ist.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:columns {\"verticalAlignment\":\"top\",\"className\":\"iss-rental-steps\"} -->\n<div class=\"wp-block-columns are-vertically-aligned-top iss-rental-steps\"><!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:group {\"className\":\"iss-rental-step\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-rental-step\"><!-- wp:paragraph {\"className\":\"iss-rental-step__number\"} -->\n<p class=\"iss-rental-step__number\">01</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3,\"className\":\"iss-rental-step__title\"} -->\n<h3 class=\"wp-block-heading iss-rental-step__title\">Anlass skizzieren</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Worum geht es, wie viele Personen sind geplant, welcher Rahmen wird gesucht und zu welchem Zeitpunkt soll die Veranstaltung stattfinden?</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:group {\"className\":\"iss-rental-step\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-rental-step\"><!-- wp:paragraph {\"className\":\"iss-rental-step__number\"} -->\n<p class=\"iss-rental-step__number\">02</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3,\"className\":\"iss-rental-step__title\"} -->\n<h3 class=\"wp-block-heading iss-rental-step__title\">Machbarkeit klären</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Wir prüfen, ob Format, Termin und Umfang zum Haus passen und welche Form der Nutzung sinnvoll und realistisch ist.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:group {\"className\":\"iss-rental-step\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-rental-step\"><!-- wp:paragraph {\"className\":\"iss-rental-step__number\"} -->\n<p class=\"iss-rental-step__number\">03</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3,\"className\":\"iss-rental-step__title\"} -->\n<h3 class=\"wp-block-heading iss-rental-step__title\">Rahmen abstimmen</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Danach werden Nutzung, Zeiten, eventuelle Zusatzwünsche und die nächsten Schritte konkret besprochen.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group -->\n\n<!-- wp:group {\"tagName\":\"section\",\"className\":\"section iss-rental-cta\",\"backgroundColor\":\"eerie-black\",\"textColor\":\"vaporous-white\",\"layout\":{\"type\":\"constrained\"}} -->\n<section id=\"anfrage\" class=\"wp-block-group section iss-rental-cta has-vaporous-white-color has-eerie-black-background-color has-text-color has-background\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:columns {\"verticalAlignment\":\"top\",\"className\":\"iss-rental-cta__grid\"} -->\n<div class=\"wp-block-columns are-vertically-aligned-top iss-rental-cta__grid\"><!-- wp:column {\"verticalAlignment\":\"top\",\"width\":\"62%\"} -->\n<div class=\"wp-block-column is-vertically-aligned-top\" style=\"flex-basis:62%\"><!-- wp:group {\"className\":\"iss-heading iss-heading\\u002d\\u002duncaged\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-heading iss-heading--uncaged\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dlight\"} -->\n<p class=\"iss-kicker iss-kicker--light\">Anfrage</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"className\":\"iss-heading__title\",\"textColor\":\"vaporous-white\"} -->\n<h2 class=\"wp-block-heading iss-heading__title has-vaporous-white-color has-text-color\">Sie planen etwas Passendes für diesen Ort?</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-heading__text has-vaporous-white-color has-text-color\"} -->\n<p class=\"iss-heading__text has-vaporous-white-color has-text-color\">Dann beschreiben Sie uns kurz Anlass, Wunschzeitraum und ungefähre Gruppengröße. Wir melden uns zurück, ob und wie sich Ihr Vorhaben im Industriesalon umsetzen lässt.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"verticalAlignment\":\"top\",\"width\":\"38%\"} -->\n<div class=\"wp-block-column is-vertically-aligned-top\" style=\"flex-basis:38%\"><!-- wp:group {\"className\":\"iss-rental-contact\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group iss-rental-contact\"><!-- wp:paragraph -->\n<p><strong>E-Mail</strong><br><a href=\"mailto:vermietung@industriesalon.de\">vermietung@industriesalon.de</a></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><strong>Telefon</strong><br><a href=\"tel:+49305309970\">030 5300 9970</a></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><strong>Sinnvoll in der Anfrage</strong><br>Anlass, Datum oder Zeitraum, Personenzahl, gewünschter Rahmen und besondere Anforderungen.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"mailto:vermietung@industriesalon.de?subject=Anfrage%20Salon-Vermietung\">Jetzt anfragen</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons --></div>\n<!-- /wp:group --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group --></main>\n<!-- /wp:group -->"
      },
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          ""
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "5326bd1999fab688e48219e49b0264089e910d280e261410a2ccdafdf66024a1",
        "post_content": "b6bb6a143d727ab979f85abb126d1bf23f37e5a0547a16c23a37e80a9c0d51b5",
        "post_title": "136d7f15f1dc537fd58936ff766c6596c132cc8a9f877e3bffc7d3dac788cee1",
        "post_excerpt": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "58195f342efca9e75bdc5dafd7acbde82744d18b59a478d129f3fce6128ce33e",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3660315a9af3df255d8f19ab077e4797822b41488a0e2a04bc6af71213c23274",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "identity": {
        "type": "page",
        "slug": "salon-vermietung"
      }
    },
    {
      "id": 12899,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "27087"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "archive_images": [
          [
            {
              "media_id": 27087,
              "url": "{{SITE_URL}}/wp-content/uploads/2026/04/treskowbruecke-gasanstalt-oberspree-1926.webp",
              "caption": "Treskowbrücke 1926, mittig die Gasanstalt Oberspree.",
              "year": "1926",
              "source": "Industriesalon/BIWAQ Schöneweide, Tafel „Nutzungsmischung aus Tradition“, 2012; https://www.yumpu.com/de/document/view/2150402/tafeln-1-5-biwaq-schoeneweide/2",
              "photographer": "Unbekannt",
              "rights": "Historische Postkarte; Urheber nicht ermittelt; Wiederverwendung aus dem Industriesalon/BIWAQ-Projekt.",
              "is_featured": true,
              "visibility": "public"
            }
          ]
        ],
        "current_images": [
          [
            {
              "media_id": 27085,
              "url": "{{SITE_URL}}/wp-content/uploads/2026/07/Wilheminenstr89-SpreehC3B6fe-Kino_28129-scaled.jpg",
              "caption": "Wilheminenstr89-Spreehöfe-Kino (1)",
              "year": "2017",
              "source": "Wikimedia Commons",
              "photographer": "Günter Haase",
              "rights": "CC BY-SA 4.0 | https://commons.wikimedia.org/wiki/File:Wilheminenstr89-Spreehfe-Kino_(1).jpg",
              "is_featured": false,
              "visibility": "pending"
            },
            {
              "media_id": 27086,
              "url": "{{SITE_URL}}/wp-content/uploads/2026/07/Wilheminenstr89-SpreehC3B6fe-Kino_28529-scaled.jpg",
              "caption": "Wilheminenstr89-Spreehöfe-Kino (5)",
              "year": "2017",
              "source": "Wikimedia Commons",
              "photographer": "Günter Haase",
              "rights": "CC BY-SA 4.0 | https://commons.wikimedia.org/wiki/File:Wilheminenstr89-Spreehfe-Kino_(5).jpg",
              "is_featured": false,
              "visibility": "pending"
            },
            {
              "media_id": 27088,
              "url": "{{SITE_URL}}/wp-content/uploads/2026/07/Berlin_OberschC3B6neweide_Gaswerk_Wasserturm_2022-scaled.jpg",
              "caption": "Berlin Oberschöneweide Gaswerk Wasserturm 2022",
              "year": "2022",
              "source": "Wikimedia Commons",
              "photographer": "Oberlausitzerin64",
              "rights": "CC BY-SA 4.0 | https://commons.wikimedia.org/wiki/File:Berlin_Oberschneweide_Gaswerk_Wasserturm_2022.jpg",
              "is_featured": false,
              "visibility": "pending"
            }
          ]
        ],
        "construction_period": [
          "1898–1906"
        ],
        "monument_record_url": [
          "https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128"
        ],
        "monument_status": [
          "Gesamtanlage"
        ],
        "original_name": [
          "Städtische Gasanstalt Oberspree"
        ],
        "previous_use": [
          "Teil des 1898 eröffneten Gaswerks Oberspree an der Wilhelminenhofstraße 88/89. Ab 1937/39 nutzte ADMOS den rückwärtigen Grundstücksteil; ab 1951 gehörte der Betrieb als Werk III zum VEB Berliner Metallhütten- und Halbzeugwerke. Zwei ehemalige Produktionshallen wurden 1998 zum Kino umgebaut."
        ],
        "place_visibility": [
          "public"
        ]
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "1cd86d41a4d6eb41f6a1f56d7bb12e28bf1eca874648ffc6a5cd2df1083ec6e9",
        "post_content": "4edbc38b0706dcb73bbcfed9d5fc25d17e0a5f1f8a097c4b45280872f1b29a57",
        "post_title": "714d61447918188e2036c975e57046122f3224e19e49437a423b52973a4b2de2",
        "post_excerpt": "289ba31aad11171c056b33dd35ec0616bf6a4d722012f5c09e082bba95bbde5e",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "3f64cacfd513a32b82091045063d73fd63a862fecb0e65427cb408f9b8573167",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a6e11e1bb31da7e119b8213067a007a635eefcf14693ce09eb716ec0a74496ce",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "archive_images": [],
        "current_images": [],
        "construction_period": [],
        "monument_record_url": [],
        "monument_status": [],
        "original_name": [],
        "previous_use": [
          "5d3ab666acd87ae95f62fd01e264469d44732a7eb792a497dc3128c9708c7ec7"
        ],
        "place_visibility": [],
        "_iss_editorial_place": [],
        "_iss_editorial_enabled_place": [],
        "_iss_editorial_place_skin": []
      },
      "identity": {
        "type": "register_place",
        "slug": "kino-spreehofe"
      },
      "format": "place",
      "document": {
        "schema_version": 1,
        "skin": "ortsdossier",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "Ort auf einen Blick",
            "body": "Eröffnet September 1998 als Kinowelt in den Spreehöfen.",
            "media_refs": []
          },
          {
            "type": "epoche",
            "kicker": "",
            "title": "Gasanstalt Oberspree",
            "body": "1898 nahm die von der Imperial Continental Gas Association errichtete Gasanstalt Oberspree auf dem Grundstück Wilhelminenhofstraße 88/89 den Betrieb auf. 1898/99 entstanden Retortenhaus sowie Maschinen- und Apparatehaus; 1905/06 folgten Kesselhaus, Hochdruckanlage und Erweiterungen.",
            "media_refs": [],
            "start_year": 1898,
            "end_year": 1918,
            "era_key": "kaiserzeit",
            "function_key": "infrastructure",
            "is_current": false,
            "source_confidence": "url",
            "source_summary": "Landesdenkmalamt Berlin, Denkmaldatenbank: Gaswerk Oberspree.",
            "source_refs": [
              {
                "label": "denkmaldatenbank.berlin.de",
                "url": "https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128"
              }
            ]
          },
          {
            "type": "epoche",
            "kicker": "",
            "title": "Gaswerk bis zum Ende der Stadtgaserzeugung",
            "body": "Das Gaswerk versorgte Schöneweide und benachbarte Ortsteile mit Stadtgas. Die Erzeugung von Stadtgas endete 1927; Teile der Speicher- und Verteilungsanlagen blieben erhalten und wurden weiter genutzt.",
            "media_refs": [
              {
                "kind": "wp_media",
                "source": "wordpress",
                "id": "27087",
                "label": "Treskowbrücke und Gasanstalt Oberspree, 1926",
                "thumbnail": ""
              }
            ],
            "start_year": 1919,
            "end_year": 1927,
            "era_key": "weimar",
            "function_key": "infrastructure",
            "is_current": false,
            "source_confidence": "url",
            "source_summary": "Landesdenkmalamt Berlin; historische Postkarte auf der Industriesalon/BIWAQ-Tafel „Nutzungsmischung aus Tradition“ (2012).",
            "source_refs": [
              {
                "label": "denkmaldatenbank.berlin.de",
                "url": "https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128"
              },
              {
                "label": "www.yumpu.com",
                "url": "https://www.yumpu.com/de/document/view/2150402/tafeln-1-5-biwaq-schoeneweide/2"
              }
            ]
          },
          {
            "type": "epoche",
            "kicker": "",
            "title": "ADMOS auf dem Gaswerksgelände",
            "body": "Ab 1937/39 übernahmen die Allgemeinen Deutschen Metallwerke Oberschöneweide (ADMOS) den rückwärtigen Teil des Gaswerksgeländes und produzierten dort hochwertige Metalllegierungen. Eine Grundstücksakte von 1939 dokumentiert die Abtrennung und geplante Bebauung.",
            "media_refs": [],
            "start_year": 1937,
            "end_year": 1945,
            "era_key": "ns-zeit",
            "function_key": "industrial",
            "is_current": false,
            "source_confidence": "url",
            "source_summary": "Landesdenkmalamt Berlin; Landesarchiv Berlin, Grundstücksakte A Rep. 046-08 Nr. 312.",
            "source_refs": [
              {
                "label": "denkmaldatenbank.berlin.de",
                "url": "https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128"
              },
              {
                "label": "www.deutsche-digitale-bibliothek.de",
                "url": "https://www.deutsche-digitale-bibliothek.de/item/MJWLVMMH2KNHVZNIQHPRISNZX4Y7GK2G"
              }
            ]
          },
          {
            "type": "epoche",
            "kicker": "",
            "title": "VEB Berliner Metallhütten- und Halbzeugwerke",
            "body": "Zum 1. Januar 1951 wurde der Oberschöneweider Betrieb als Werk III in den VEB Berliner Metallhütten- und Halbzeugwerke eingegliedert. Der große Gasbehälter auf dem Areal blieb bis 1974 in Betrieb.",
            "media_refs": [],
            "start_year": 1951,
            "end_year": 1990,
            "era_key": "ddr",
            "function_key": "industrial",
            "is_current": false,
            "source_confidence": "url",
            "source_summary": "Landesdenkmalamt Berlin; Industriesalon Schöneweide / Deutsche Digitale Bibliothek.",
            "source_refs": [
              {
                "label": "denkmaldatenbank.berlin.de",
                "url": "https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128"
              },
              {
                "label": "www.deutsche-digitale-bibliothek.de",
                "url": "https://www.deutsche-digitale-bibliothek.de/item/X2T4JHIXVTV5LZRU4XOSPG4WYU4WGAXS"
              }
            ]
          },
          {
            "type": "epoche",
            "kicker": "",
            "title": "Rückbau und Umnutzung zu den Spreehöfen",
            "body": "Nach 1990 wurden Teile des Retorten- und Kesselhauses für Parkplatz, Kino und Gewerbe abgebrochen. 1993 wurde das Eisengerüst des großen Gasbehälters demontiert; anschließend entwickelte sich das Areal zu den Spreehöfen.",
            "media_refs": [],
            "start_year": 1991,
            "end_year": 1997,
            "era_key": "nach-1990",
            "function_key": "mixed",
            "is_current": false,
            "source_confidence": "url",
            "source_summary": "Landesdenkmalamt Berlin, Denkmaldatenbank: Gaswerk Oberspree.",
            "source_refs": [
              {
                "label": "denkmaldatenbank.berlin.de",
                "url": "https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128"
              }
            ]
          },
          {
            "type": "epoche",
            "kicker": "",
            "title": "Kino Spreehöfe",
            "body": "Zwei ehemalige Produktionshallen von ADMOS/BMHW wurden zu fünf Kinosälen umgebaut. Die „Kinowelt in den Spreehöfen“ eröffnete am 23. September 1998. Nach dem Betreiberwechsel 2001 wird das Haus seit dem 2. Februar 2006 als Kino Spreehöfe geführt.",
            "media_refs": [],
            "start_year": 1998,
            "end_year": null,
            "era_key": "nach-1990",
            "function_key": "culture",
            "is_current": true,
            "source_confidence": "url",
            "source_summary": "Berliner Monatszeitschrift 11/1998; Kino Spreehöfe, Über uns.",
            "source_refs": [
              {
                "label": "berlingeschichte.de",
                "url": "https://berlingeschichte.de/bms/bmstext/9811dokb.htm"
              },
              {
                "label": "www.kino-spreehoefe.de",
                "url": "https://www.kino-spreehoefe.de/unterseite/6618/%C3%9Cber_uns"
              }
            ]
          },
          {
            "type": "gegenwart",
            "kicker": "",
            "title": "Heute",
            "body": "Aktiver Kinobetrieb mit mehreren Sälen. Rahmenplan: Maßnahme C2: Kaisersteg-Platz als Schlüsselmaßnahme – neue Platzgestaltung am Kaisersteg NSW.",
            "media_refs": [],
            "links": []
          },
          {
            "type": "upload_intake",
            "kicker": "",
            "title": "Wissen Sie mehr über diesen Ort?",
            "body": "Eigene Fotos, Erinnerungen oder Korrekturen helfen, die Geschichte dieses Ortes vollständiger zu erzählen.",
            "links": []
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 13425,
      "new": false,
      "post": {
        "post_content": ""
      },
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "76456170d9cdf9e68581dc3033e9b23b9964ac822b0e74edf65698204f9653ca",
        "post_content": "128d7f32599520b42ddab0b7070dea84591b1539667fd9fdeaf5c40870802214",
        "post_title": "916575282a4f5ca713c9520b75d853af0107464fad8b684a04289489253d1dda",
        "post_excerpt": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "b419e1ccc69a653bc25f67f91bb22b79d9759bc76c57da4b140e223221d0f244",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3660315a9af3df255d8f19ab077e4797822b41488a0e2a04bc6af71213c23274",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "identity": {
        "type": "page",
        "slug": "register-schoneweide"
      }
    },
    {
      "id": 17980,
      "new": false,
      "post": {
        "post_content": "<!-- wp:paragraph {\"className\":\"iss-ausstellung-lede\"} -->\r\n<p class=\"iss-ausstellung-lede\">Diese Ausstellung führt durch die Geschichte der Kinderbetreuung im Werk für Fernmeldewesen und im WF. Neun Stationen verbinden betriebliche Sozialpolitik, Familienalltag und Archivobjekte.</p>\r\n<!-- /wp:paragraph -->\r\n<p>&nbsp;</p>\r\n<!-- wp:paragraph {\"className\":\"iss-ausstellung-lede\"} -->\r\n<p class=\"iss-ausstellung-lede\">Der Pfad folgt neun Stationen: vom ersten betrieblichen Betreuungsbedarf über Wochenheime und Krippen bis zur späteren Kritik an der wochenweisen Unterbringung von Kindern.</p>\r\n<!-- /wp:paragraph -->\r\n<p>&nbsp;</p>\r\n<!-- wp:group {\"className\":\"iss-ausstellung-station-nav\",\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group iss-ausstellung-station-nav\"><!-- wp:heading {\"level\":2,\"anchor\":\"stationen\"} -->\r\n<h2 id=\"stationen\" class=\"wp-block-heading\">Stationen</h2>\r\n<!-- /wp:heading -->\r\n\r\n<!-- wp:list {\"ordered\":true} -->\r\n<ol>\r\n<li><a href=\"#station-arbeit-betreuung\">Station 1: Arbeit, Kinder und Produktionsalltag</a></li>\r\n<li><a href=\"#station-agnes-smedley\">Station 2: Das Kinderwochenheim Agnes Smedley</a></li>\r\n<li><a href=\"#station-neue-muehle\">Station 3: Neue Mühle: Wochenheim außerhalb des Werks</a></li>\r\n<li><a href=\"#station-wochenkrippen\">Station 4: Wochenkrippen in der Ostendstraße</a></li>\r\n<li><a href=\"#station-tagesplaetze\">Station 5: Mehr Tagesplätze, andere Erwartungen</a></li>\r\n<li><a href=\"#station-alltag-auf-dem-werksgelaende\">Station 6: Kindheit am Rand des Werksgeländes</a></li>\r\n<li><a href=\"#station-kranke-kinder\">Station 7: Wenn Betreuung Arbeit absichern soll</a></li>\r\n<li><a href=\"#station-einweisung-und-regeln\">Station 8: Einweisung, Regeln und Verantwortung</a></li>\r\n<li><a href=\"#station-ambivalenz-und-rueckblick\">Station 9: Ambivalenz und Rückblick</a></li>\r\n</ol>\r\n<!-- /wp:list --></div>\r\n<!-- /wp:group -->\r\n<p>&nbsp;</p>\r\n<!-- wp:group {\"className\":\"iss-ausstellung-station\",\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group iss-ausstellung-station\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\r\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">Station 1</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:heading {\"level\":3,\"anchor\":\"station-arbeit-betreuung\"} -->\r\n<h3 id=\"station-arbeit-betreuung\" class=\"wp-block-heading\">Arbeit, Kinder und Produktionsalltag</h3>\r\n<!-- /wp:heading -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Die Ausstellung beginnt mit einer Betriebszeitung von 1954. Auf der Titelseite werden Kinderkrippe und Produktion direkt zusammengedacht: Wenn Kinder betreut sind, können ihre Mütter im Werk arbeiten.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Daran wird der Grundkonflikt sichtbar. Kinderbetreuung war Fürsorge, aber auch betriebliche Infrastruktur. Sie sollte Familien entlasten und zugleich Arbeitskräfte im HF und später im WF halten.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":0,\"variant\":\"featured\"} /--></div>\r\n<!-- /wp:group -->\r\n<p>&nbsp;</p>\r\n<!-- wp:group {\"className\":\"iss-ausstellung-station\",\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group iss-ausstellung-station\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\r\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">Station 2</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:heading {\"level\":3,\"anchor\":\"station-agnes-smedley\"} -->\r\n<h3 id=\"station-agnes-smedley\" class=\"wp-block-heading\">Das Kinderwochenheim Agnes Smedley</h3>\r\n<!-- /wp:heading -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>1949 pachtete das OSW ein Haus in der Ostendstraße 10. Nach Renovierung und freiwilligen Arbeitseinsätzen wurde dort 1950 ein Kindergarten eröffnet.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Aus dem Kindergarten wurde bald ein Kinderwochenheim. Kinder konnten montags abgegeben und erst am Wochenende wieder abgeholt werden. Die Einrichtung Agnes Smedley stand damit für eine Form der Betreuung, die Familie und Betrieb radikal neu organisierte.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":1,\"variant\":\"featured\"} /--></div>\r\n<!-- /wp:group -->\r\n<p>&nbsp;</p>\r\n<!-- wp:group {\"className\":\"iss-ausstellung-station\",\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group iss-ausstellung-station\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\r\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">Station 3</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:heading {\"level\":3,\"anchor\":\"station-neue-muehle\"} -->\r\n<h3 id=\"station-neue-muehle\" class=\"wp-block-heading\">Neue Mühle: Wochenheim außerhalb des Werks</h3>\r\n<!-- /wp:heading -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Das Objekt Neue Mühle in Königs Wusterhausen kam vermutlich über die NEF in den betrieblichen Besitz. Zunächst diente es als Ferienheim für Werksangehörige.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Ab 1952 wurde daraus ein Kinderwochenheim. Die Kinder wurden im Kinderheim Agnes Smedley gesammelt und mit dem Bus nach Königs Wusterhausen gebracht. Die Station zeigt, wie weit der Betrieb sein soziales Netz räumlich ausdehnte.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":2,\"variant\":\"featured\"} /--></div>\r\n<!-- /wp:group -->\r\n<p>&nbsp;</p>\r\n<!-- wp:group {\"className\":\"iss-ausstellung-station\",\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group iss-ausstellung-station\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\r\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">Station 4</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:heading {\"level\":3,\"anchor\":\"station-wochenkrippen\"} -->\r\n<h3 id=\"station-wochenkrippen\" class=\"wp-block-heading\">Wochenkrippen in der Ostendstraße</h3>\r\n<!-- /wp:heading -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Mit der Kinderkrippe Ethel und Julius Rosenberg entstand 1953 eine weitere betriebliche Einrichtung. Auch sie war anfangs als Wochenkrippe gedacht.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Später kamen Tagesplätze hinzu, die Nachfrage verschob sich. Die Entwicklung der Krippen zeigt den Wandel von einer wochenweisen Unterbringung hin zu alltäglicher Betreuung mit mehr Nähe zur Familie.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":3,\"variant\":\"featured\"} /--></div>\r\n<!-- /wp:group -->\r\n<p>&nbsp;</p>\r\n<!-- wp:group {\"className\":\"iss-ausstellung-station\",\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group iss-ausstellung-station\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\r\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">Station 5</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:heading {\"level\":3,\"anchor\":\"station-tagesplaetze\"} -->\r\n<h3 id=\"station-tagesplaetze\" class=\"wp-block-heading\">Mehr Tagesplätze, andere Erwartungen</h3>\r\n<!-- /wp:heading -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>In den 1960er und 1970er Jahren veränderte sich die Nutzung der Einrichtungen. Wochenplätze wurden weniger gefragt, Tagesplätze wurden wichtiger.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Das hatte mit Familienbildern, Arbeitszeiten, Wohnverhältnissen und sozialpolitischen Maßnahmen zu tun. Die Ausstellung liest die Bilder deshalb nicht nur als Kinderszenen, sondern als Spuren betrieblicher Sozialpolitik.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":4,\"variant\":\"featured\"} /--></div>\r\n<!-- /wp:group -->\r\n<p>&nbsp;</p>\r\n<!-- wp:group {\"className\":\"iss-ausstellung-station\",\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group iss-ausstellung-station\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\r\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">Station 6</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:heading {\"level\":3,\"anchor\":\"station-alltag-auf-dem-werksgelaende\"} -->\r\n<h3 id=\"station-alltag-auf-dem-werksgelaende\" class=\"wp-block-heading\">Kindheit am Rand des Werksgeländes</h3>\r\n<!-- /wp:heading -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Spielplätze, Sandkästen und Kindergruppen gehörten zum erweiterten Betriebsalltag. Die Einrichtungen lagen nicht außerhalb der Werksgeschichte, sondern waren Teil ihrer sozialen Infrastruktur.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Diese Station macht sichtbar, dass sich die Geschichte des WF nicht nur in Werkhallen, Produkten und Plänen abspielt. Sie reicht in Familien, Wege, Pausen und Sorgearbeit hinein.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":5,\"variant\":\"featured\"} /--></div>\r\n<!-- /wp:group -->\r\n<p>&nbsp;</p>\r\n<!-- wp:group {\"className\":\"iss-ausstellung-station\",\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group iss-ausstellung-station\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\r\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">Station 7</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:heading {\"level\":3,\"anchor\":\"station-kranke-kinder\"} -->\r\n<h3 id=\"station-kranke-kinder\" class=\"wp-block-heading\">Wenn Betreuung Arbeit absichern soll</h3>\r\n<!-- /wp:heading -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Auch leicht erkrankte Kinder wurden zum Thema betrieblicher Organisation. Eine Krankenstation für Kinder sollte Ausfälle der Eltern reduzieren und gleichzeitig Versorgung bieten.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Gerade an solchen Quellen wird die Ambivalenz deutlich: Hilfe für Familien und Stabilisierung der Produktion greifen ineinander. Die Ausstellung lässt diese Spannung stehen, statt sie glattzuziehen.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":6,\"variant\":\"featured\"} /--></div>\r\n<!-- /wp:group -->\r\n<p>&nbsp;</p>\r\n<!-- wp:group {\"className\":\"iss-ausstellung-station\",\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group iss-ausstellung-station\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\r\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">Station 8</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:heading {\"level\":3,\"anchor\":\"station-einweisung-und-regeln\"} -->\r\n<h3 id=\"station-einweisung-und-regeln\" class=\"wp-block-heading\">Einweisung, Regeln und Verantwortung</h3>\r\n<!-- /wp:heading -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Die Vergabe von Krippen- und Kindergartenplätzen war geregelt. Bis 1973 entschied eine betriebliche Einweisungskommission, danach stärker eine zentrale Stelle beim Rat des Stadtbezirks.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Damit wechselte die Kinderbetreuung zwischen Betrieb, Kommune und Familie. Die Quelle zur BVV macht diese Verwaltungsseite sichtbar: Betreuung war nicht nur Alltagspraxis, sondern auch Aktenlage und Entscheidungssystem.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":7,\"variant\":\"featured\"} /--></div>\r\n<!-- /wp:group -->\r\n<p>&nbsp;</p>\r\n<!-- wp:group {\"className\":\"iss-ausstellung-station\",\"layout\":{\"type\":\"constrained\"}} -->\r\n<div class=\"wp-block-group iss-ausstellung-station\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\r\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">Station 9</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:heading {\"level\":3,\"anchor\":\"station-ambivalenz-und-rueckblick\"} -->\r\n<h3 id=\"station-ambivalenz-und-rueckblick\" class=\"wp-block-heading\">Ambivalenz und Rückblick</h3>\r\n<!-- /wp:heading -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Wochenkrippen und Wochenheime werden heute auch mit Blick auf Bindung, Trennungserfahrungen und psychische Folgen diskutiert. Die historischen Quellen des WF zeigen, dass diese Fragen schon im Alltag der Einrichtungen angelegt waren.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:paragraph -->\r\n<p>Die Ausstellung endet deshalb nicht mit einem einfachen Urteil. Sie zeigt Kinderbetreuung als notwendige Entlastung, als Produktionsbedingung, als staatlich-betriebliche Ordnung und als Erfahrung, die für Kinder und Eltern sehr unterschiedlich gewesen sein konnte.</p>\r\n<!-- /wp:paragraph -->\r\n\r\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":8,\"variant\":\"featured\"} /--></div>\r\n<!-- /wp:group -->\r\n<p>&nbsp;</p>"
      },
      "meta": {
        "_thumbnail_id": [
          "13821"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "1"
        ],
        "iss_archive_browser_lock_field": [
          ""
        ],
        "iss_archive_browser_lock_source": [
          ""
        ],
        "iss_archive_browser_show_source_cards": [
          ""
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "f31c23a50881c6630b3f41f81cb76f5748e8620d2bc939ed5a499bba126e74b3",
        "post_content": "6059fa35ba047c0a90510fff37502082de39cc82efb60b525a91441087536afc",
        "post_title": "70dbade8ea1bff05ae14fc61a27a458b043c705c0096584bc7a5b8c97da92d4e",
        "post_excerpt": "56c960c0c1aa792594b35a9a952c1f56895c82667f01f8f434fd10bc1881b7cf",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "89560de551cc8c2bbd3b4260cb525df1b642b1255681ee54066c7a7076db18e5",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "2da014ed7929684373349743ff3e76fe8eb0427cc12e51d0040ea515b340a205"
        ],
        "_wp_page_template": [
          "1080a60800d9e5bddc69f51489c636a776bacaadb5368f46db4aa0947c67e3ba",
          "1080a60800d9e5bddc69f51489c636a776bacaadb5368f46db4aa0947c67e3ba"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126",
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "iss_archive_browser_lock_source": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126",
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "iss_archive_browser_show_source_cards": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126",
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "kinder-im-wf"
      }
    },
    {
      "id": 18864,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "2784"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          0
        ],
        "_iss_publication_price_cents": [
          0
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "d5d6dff3b95b3fdad554a6135fc66cb0f70d868afaa9b8cf9a9c2858879a300f",
        "post_content": "2214d3688c0ce171dc9e91c3ee83f745e6ef90dba2c88363e8e7a68a29d73d30",
        "post_title": "11adda13bfe60d33d2beca2d05669ec7ee23a2a06a035d7c60dcc6d0c24f0881",
        "post_excerpt": "9a646dbd66e15557fc751e9d8c5a82dd90f43b072804da0c76b17af68bd49af0",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "4f9ce6167366505e6e42442e951b4111d618a5d3a9fc1bf2295457c9a3899f06",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "91dcd9e8e37430e1783349ae25bbd840ebc1ebe827e7545328b1f084bda066af"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "transformatorenwerk-oberschoeneweide-chronik"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "source",
            "kicker": "",
            "title": "Quelle / Rechte",
            "body": "Quellengrundlage: lokal gesicherter Touchtable-Bestand des Industriesalon Schöneweide.",
            "links": []
          },
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Chronik",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Großbanken und Industriebetriebe unter Beteiligung der AEG gründeten die Aktiengesellschaft „Deutsche Niles – Werkzeugmaschinenfabrik“ als Lizenznehmer der amerikanischen Niles Tool Campany in Ohio. Ziel war es, die nationale Fertigung zu stärken und die bis dahin führenden amerikanischen Produkte vom Markt zu verdrängen.</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13726",
                "label": "Transformatorenwerk Oberschöneweide 1898",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1898-300x285.webp"
              }
            ],
            "year": "1898"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Ansicht Niles 1898. Nach den Plänen von Baumeister Tropp wurde ein Verwaltungs-, ein Produktionsgebäude und eine Montagehalle erbaut. Das Gelände mit rund 72.000 Quadratmetern war an die Görlitzer Eisenbahn angebunden und hatte eigene Kaianlagen an der Spree. Eine gute Ausgangssituation für das wirtschaftliche Wachstum.</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13730",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1899-300x192.webp"
              }
            ],
            "year": "1899"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Foto: In der Niles-Montagehalle um 1900. Die Produktion begann mit etwa 1.0000 Mitarbeitern. Es wurden u. a Drehbänke, Fräsmaschinen. Hobel-, Stoß- und Bohrmaschinen, später auch Pressluftwerkzeuge hergestellt.</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13731",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1901-300x230.webp"
              }
            ],
            "year": "1901"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Das Unternehmen stieg zu den weltweit bedeutendsten Herstellern von Präzisions-Werkzeugmaschinen auf. Auf der idealisierten Darstellung ist die historische Bebauung gut zu erkennen. Das Gelände wurde auf einer Nord-Süd-Achse, von der Wilhelminenhofstraße bis zur Spree, erschlossen. Im Vordergrund die Wilhelminenhofstraße mit kleinem…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13732",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1906-1-300x155.webp"
              }
            ],
            "year": "1906"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Produktion von Granathülsen bei Niles in Oberschöneweide. Mit dem Beginn des ersten Weltkriegs setzte Großbritannien eine Blockade für den deutschen Im- und Export durch. Das betraf neben Waffen und militärischen Ausrüstungen und Transportmitteln auch alle Maschinen, die der Produktion oder Reparatur von Waffen oder Munition dienen…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13733",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1914-300x241.webp"
              }
            ],
            "year": "1914"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Sondermaschine mit der Markenkennzeichnung „MOAG“. Umbenennung von Niles in „Maschinenfabrik Oberschöneweide AG“ (MOAG). Als die USA in den 1. Weltkrieg eintraten, schien die Bezeichnung einer deutschen Firma mit dem Namen einer amerikanischen Fabrik nicht mehr tragbar. Das Unternehmen wurde dazu gezwungen sich umzubenennen und…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13734",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1915-256x300.webp"
              }
            ],
            "year": "1915"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Niles zieht um",
            "body": "<!-- wp:paragraph -->\n<p>Nach dem ersten Weltkrieg wurde das Unternehmen zurückbenannt in „Deutsche Niles Werke AG“. Alte Geschäftsbeziehungen wurden erfolgreich reaktiviert. Produktionsanlagen mussten erweitert und modernisiert werden. Statt Einzelanfertigungen stellte man auf Serienproduktion um. In Berlin-Weißensee konnten ein großes Grundstück und die…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13735",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1920-1-300x190.webp"
              }
            ],
            "year": "1920"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Das Transformatorenwerk der AEG",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Die „neue Montagehalle“ wurde 1915-16 von dem Architekten Paul Tropp für die Deutsche Niles -Werkzeugmaschinenfabrik erbaut und von der AEG Transformatorenfabrik übernommen. Nach dem Kauf der leerstehenden Anlage verlagerte die AEG die bisherige „Fabrik für Hochspannungs-Übertragung“ aus dem Wedding nach Oberschöneweide. 1921 nahm…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13736",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1920_1-300x217.webp"
              }
            ],
            "year": "1920"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Hervorragendster Wegbereiter der Hochspannungstechnik seiner Zeit.",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Georg Stern (1867 bis 1934) Physiker, Ingenieur und erster Direktor der AEG Transformatorenfabrik TRO von 1921 bis 1931. Georg Stern prägte maßgeblich die Entwicklung des (Groß-) Transformatorenbaus der AEG. Bereits im Jahr 1901 hatte er die Leitung der Prüffelder der AEG-Maschinenfabrik in der Berliner Brunnenstraße übernommen…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13737",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1921_1-210x300.webp"
              }
            ],
            "year": "1921"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Foto: 20iger Jahre, Transformatorenbau in der neuen Montagehalle im TRO. Am 1. September 1923 bebte die Erde in Japan. Das Große Kantō-Erdbeben war eine der schwersten Katastrophen in der japanischen Geschichte. Es zerstörte weite Teile von Tōkyō und Yokohama, forderte über 100.000 Todesopfer und legte große Teile der Infrastruktur –…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13738",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1923-232x300.webp"
              }
            ],
            "year": "1923"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Streik!",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Wickler an der Spulen-Spezialmaschine. Drei Wochen lang dauerte der Streik der Wickler im Transformatorenwerk. Es ging um die Erhöhung des Stundenlohns von 44 Pfennig, im Akkord maximal 80 Pfennig. Auch die 48-stündige Wochenarbeitszeit drohte auf 50 Stunden ausgeweitet zu werden. Die Gewerkschaften hatten bis dahin einen…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13739",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/streik1-300x198.webp"
              }
            ],
            "year": "1924"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Ein Großauftrag für das TRO",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Georg Klingenberg – (1870-1925) Ingenieur, Autokonstrukteur, Kraftwerksbauer. Ab 1902 Vorstandsmitglied der AEG. Auf Beschluss der Bezirksverordnetenversammlung Lichtenberg sollte die Stromversorgung des Berliner Ostens verbessert werden. Der Auftrag für den schlüsselfertigen Bau eines Steinkohlekraftwerks mit 270 MW Leistung ging…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13740",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1926_2-205x300.webp"
              }
            ],
            "year": "1926"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Die Kraft- und Heizzentrale",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Kraftwerksgebäude im Stil des Expressionismus. Das betriebseigene Heiz- und Kraftwerk des TRO wurde nach zweijähriger Bauzeit fertiggestellt. Die Architekten Walter Klingenberg und Werner Issel entwarfen das markante Gebäude mit einer innovativen technischen Ausstattung. In den mit Kohlenstaub angefeuerten Kesseln wurde Dampf…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13741",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1928_2-300x225.webp"
              }
            ],
            "year": "1928"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Pionierleistungen",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Der weltgrößte Transformator aus Oberschöneweide. Im TRO wurde der riesige Einphasen-Transformator entwickelt und gebaut, der zu seiner Zeit der größte der Welt war. Der Transformator wog über 100 Tonnen und wurde mit Spezialzügen direkt aus der Halle transportiert. Er diente der Umwandlung von Strom aus dem öffentlichen Netz in…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13742",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1928_1-295x300.webp"
              }
            ],
            "year": "1928"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Schauplatz der Moderne",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Höchste Montagehalle Berlins. Das Transformatorenwerk baute immer größere Transformatoren, für die deutlich höhere Räume benötigt wurden. Nach den Entwürfen des Architekten Ernst Ziesel und der Statikers Gerhard Mensch entstand die Großtransformatorenhalle. Zwei übereinanderliegende Kranbahnen von höchster Tragkraft (100 t) konnten…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13743",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1929_3-300x238.webp"
              }
            ],
            "year": "1929"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Atomzertrümmerung im TRO",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Der Blitzgenerator auf dem Dach der Montagehalle. Um die Wetterfestigkeit der freistehenden Transformatoren zu prüfen, wurden sie mit sehr starken Blitzen beschossen. Das TRO verfügte seit den 20iger Jahren dafür über den leistungsstärksten „Stoßspannungsgenerator“, der damaligen Welt. Er konnte Spannungen bis 2,4 MV generieren…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13727",
                "label": "Transformatorenwerk Oberschöneweide 1932",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1930_1-300x219.webp"
              }
            ],
            "year": "1930"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>\"Wer noch nie bei Siemens war, bei AEG oder Borsig, der kennt des Lebens Jammer nicht, der hat ihn erst noch vor sich!\" (Berliner Spruch aus den 30iger Jahren) Die Weltwirtschaftskrise Anfang der 1930er Jahre machte auch vor dem TRO nicht Halt. Die Zahl der Aufträge ging stark zurück, es kam zu Massenentlassungen. 1932 war die Zahl der…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13745",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1932-300x238.webp"
              }
            ],
            "year": "1932"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Gesprengte Treskowbrücke. In den ersten Monaten nach Kriegsende kämpfte die Belegschaft gegen die allgegenwärtigen Kriegsschäden und beteiligte sich an der „Enttrümmerung“. „Die klassenbewussten Arbeiter… leisteten Aufräumungsarbeiten im doppelten Sinne. Schritt für Schritt begannen sie mit der Produktion, legten Maschinen aus dem…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13728",
                "label": "Transformatorenwerk Oberschöneweide 1946",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1946-300x169.webp"
              }
            ],
            "year": "1946"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>1947 wurde das Werk der sowjetischen Militäradministration unterstellt. Prüffelder und Krananlagen wurden teilweise mehrfach demontiert und nach Russland abtransportiert. Man schätzt, dass Reparationszahlungen im Wert von 2,4 Mill. DM erbracht wurden. Die Produktion von Kleintransformatoren und Hochspannungsgeräten begann dennoch langsam…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [],
            "year": "1947"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Foto: TRO-Werkseingang – zwischen AEG und „volkseigenem Betrieb“ (VEB). Auf Grundlage des Magistratsbeschlusses vom 8. Februar 1949 wurden die Eigentümer des TRO – die AEG -- entschädigungslos enteignet. Das Werk wurde zum „Volkseigenen Betrieb“ (VEB) und umbenannt in: \"VEB AEG-Fabriken für Transformatoren und Hochspannungsschalter. Mit…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13749",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_ab_1949-300x225.webp"
              }
            ],
            "year": "1949"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Nach der Teilung Deutschlands musste die DDR neue Energieressourcen erschließen - vor allem für die Entwicklung der Großindustrie. Für das \"Kohle- und Energieprogramm der DDR\" (1956) wurden neue Kohle-Kraftwerke in der Lausitz gebaut. Auf Befehl des Zentralkomitees (ZK) der Sowjetunion sollten das TRO die benötigten Großtransformatoren…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13750",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_ab_1949_1-300x207.webp"
              }
            ],
            "year": "1949"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Nach der Teilung Deutschlands musste die DDR neue Energieressourcen erschließen - vor allem für die Entwicklung der Großindustrie. Für das \"Kohle- und Energieprogramm der DDR\" (1956) wurden neue Kohle-Kraftwerke in der Lausitz gebaut. Auf Befehl des Zentralkomitees (ZK) der Sowjetunion sollten das TRO die benötigten Großtransformatoren…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13750",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_ab_1949_1-300x207.webp"
              }
            ],
            "year": "1970"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Der Trolli",
            "body": "<!-- wp:paragraph -->\n<p>Foto: DDR-Werbebroschüre. Wie jeder Großbetrieb in der DDR, so wurde auch das TRO verpflichtet, „Konsumgüter“ herzustellen. Ab Anfang der 70iger Jahre ging der Rasenmäher Trolli in die Produktion. Hintergrund: In der DDR wuchs die Kaufkraft schneller als das Angebot an Waren. Da die Konsumwünsche der Kunden nicht abgedeckt werden konnten…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13751",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/der_trolli_1-300x168.webp"
              }
            ],
            "year": "1970"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Foto: „Das TRO erfüllt Weltmarktforderungen“ - Präsentation auf der Leipziger Messe. Die Leistungsanforderungen wuchsen weiter. 1981 wurde mit 6.450 MVA die Höchstleistung in der gesamten Betriebsgeschichte realisiert. Um auf dem Weltmarkt konkurrenzfähig zu bleiben, mussten fortlaufend neue Technologien und verbesserte Materialien…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13729",
                "label": "Transformatorenwerk Oberschöneweide 1981",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_ab_1980-300x214.webp"
              }
            ],
            "year": "1980"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Explosion im Kraftwerk Bloxberg, dem größten Kohlekraftwerk der DDR",
            "body": "<!-- wp:paragraph -->\n<p>Foto: „Winterkampf“ im Kraftwerk Boxberg - Foto: Sächsische Zeitung. Im Winter 1986/1987 herrschte extreme Kälte. Bei bis zu minus 20 Grad stieg der Strombedarf enorm, die Kraftwerke waren im „Winterkampf“. Im Kraftwerk Boxberg gefror die Kohle zu Eisklumpen. Um dennoch ausreichend Dampf für die Turbinen zu produzieren, musste der…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13757",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1987-300x169.webp"
              }
            ],
            "year": "1987"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Bis 1989 waren die Auftragsbücher des TRO voll.",
            "body": "<!-- wp:paragraph -->\n<p>Der Aufbau moderner Infrastruktur im Werk wurde vorbereitet, neue Gebäude errichtet. Für die Werksangehörigen gab es keinen Zweifel an der erfolgreichen Zukunft des TRO.</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13758",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_1989-300x171.webp"
              }
            ],
            "year": "1989"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>TRO-Broschüre „Elektroenergie zuverlässig beherrschen“</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13759",
                "label": "transformatorenwerk-oberschoeneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TRO_broschuere-217x300.webp"
              }
            ],
            "year": "1989"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 18865,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "3828"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "0"
        ],
        "_iss_publication_price_cents": [
          "0"
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "a6f1b9e2c933a967c1ad070352bada329a1739b25364ffddf51059a04dbeed3f",
        "post_content": "a9bad34c8ced3b4851dcea2410ca5f40096210a1677592f6bd4a32cb9b850c96",
        "post_title": "1e3f3ac0d4e8f313ca7e2d138699c2b1d9df7e2a000f9c5267d68767e2af29db",
        "post_excerpt": "707e3b8dae51e79d7b38cf2fba0eced12d47d511b7999824de7b1d50f3c96ec9",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "bdee19cef7fb180253c17f8d5efbfa0cf8f93dfd1cc44c6c827eb324a66edcb0",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "264f3a1dadbe8c9c22aa4e07da9e5238b608fa56c04c138d943614aa91481821"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_publication_price_cents": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "kabelwerk-oberspree-chronik"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Chronik",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Emil Rathenau",
            "body": "<!-- wp:paragraph -->\n<p>1882 erwarb Emil Rathenau, Gründer und späterer Generaldirektor der Allgemeinen Elektrizitäts-Gesellschaft (AEG), die Rechte für die Nutzung der Edison-Patente in Deutschland. Eine vertraglich geregelte Interessenabgrenzung mit Siemens ließ der AEG freie Hand im Konzessions- und Finanzierungsgeschäft. Innerhalb von wenigen Jahren gelang…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13760",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/emil_rathenau-239x300.jpg"
              }
            ],
            "year": "1882"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Kraftwerk Oberspree – „Kaufhaus der Energie“",
            "body": "<!-- wp:paragraph -->\n<p>Das Engagement der AEG in Schöneweide begann mit dem Bau des ersten Drehstrom-Kraftwerks in Deutschland. Mit der neuen Drehstromtechnologie war es erstmals möglich, elektrische Energie außerhalb der Stadt zu produzieren und verlustfrei über große Entfernungen zu transportieren. Architekt Paul Tropp gestaltete die Maschinenhalle mit einer…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13761",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Kaufhaus_der_Energie01-300x130.webp"
              }
            ],
            "year": "1895"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Die Drahtfabrik im KWO, 1895, Architekt Paul Tropp",
            "body": "<!-- wp:paragraph -->\n<p>KWO im Aufbau: 1895 kaufte Emil Rathenau ein Areal mit 92.000 qm direkt neben dem Kraftwerk für den Bau des Kabelwerks Oberspree (KWO). Die schnell wachsende Fabrikanlage galt als sehr modern, alle Maschinen und Transportmittel waren komplett elektrifiziert. Die Produktionsgebäude wurden in Stahlskelettbauweise errichtet, die weiten…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13762",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/KWO-im-Aufbau_1895_01-300x215.webp"
              }
            ],
            "year": "1895"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Die damals größte Kabelfabrik Europas",
            "body": "<!-- wp:paragraph -->\n<p>Das Kabelwerk wurde von der AEG als Kombination verschiedener Betriebe so gebaut, dass alle Produktionsschritte für Kabel und Leitungen im Werk hergestellt werden konnten. Im Herbst 1897 nimmt das KWO mit 500 Beschäftigten den Betrieb auf. Im damaligen Geschäftsbericht heißt es:\"Schon im Verlauf von wenigen Monaten steigerte sich…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13763",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/kwo_1897-300x233.webp"
              }
            ],
            "year": "1897"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Erster KWO-Direktor: Erich Rathenau (*26.08.1871 † 19.01.1903)",
            "body": "<!-- wp:paragraph -->\n<p>Wie sein Vater war auch Erich Rathenau gelernter Maschinenbauer. Er war dessen große Hoffnung und sollte sein Nachfolger werden. Da er von klein auf gesundheitlich labil war, fuhren die Eltern oft mit ihm zu Kuren. Erich war begeistert von neuen Technologien und leitete den Aufbau des Kabelwerks. Doch dann verstarb er unerwartet auf…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13764",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/kwo_1897_01-300x300.webp"
              }
            ],
            "year": "1897"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Forschung im Kabelwerk",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Antennen zwischen den Schornsteinen. Das KWO war auch Forschungszentrum für drahtlose Telegrafie. Unter der Leitung von Erich Rathenau wurden im KWO ein Telefonwerk und ein Hochspannungslabor für die Entwicklung der drahtlosen Signalübertragung eingerichtet. Das „Slaby-Arco System“ bestand aus Sende- und Empfangsanlagen. Zwischen…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13765",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/1899_01-288x300.webp"
              }
            ],
            "year": "1899"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "KWO als Attraktion",
            "body": "<!-- wp:paragraph -->\n<p>In mehreren Bauphasen entstand ein einheitliches Ensemble aus Hallen und Stockwerksfabriken. Das gesamte Werk war von Beginn an komplett elektrisiert. 1900 waren bereits 800 Elektromotoren installiert, mehr als 2.000 Glühlampen und 400 Bogenlampen erleuchteten den Betrieb. Für die Berliner und ihre Gäste gehörte das moderne Kabelwerk…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13766",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/KWO_als_Attraktion-1-300x214.webp"
              }
            ],
            "year": "1900"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Automobilbau im Kabelwerk",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Mit dem „Klingenbergwagen“ stieg die AEG in Schöneweide in den Automobilbau ein. Emil Rathenau erkannte früh, dass dem Automobil die Zukunft gehört. Das KWO bot ausreichend Platz und technische Möglichkeiten für die Produktion. Um 1900 startete die AEG erste Versuche mit der Herstellung des „Klingenberg-Wagens“ - mit einem…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13767",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/1900_automobilbau-300x192.webp"
              }
            ],
            "year": "1900"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Für den wachsenden Strombedarf mussten immer höhere Spannungen in immer größere Entfernungen übertragen werden. Im KWO wurde intensiv nach neuen Isolierstoffen für Kabel geforscht. Ziel war die Unabhängigkeit von importierten Rohmaterialien wie Kuper und Gummi. Um Zulieferer zu ersetzen, wurde eine eigenen Metallbearbeitung…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13768",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/KWO_1904-300x193.webp"
              }
            ],
            "year": "1904"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Das Kabelwerk entwickelte sich zum größten und wichtigsten AEG-Betrieb mit 8.000 Arbeitern. Produziert, entwickelt und geforscht wurde in den Bereichen Elektrotechnik, Metallindustrie und Maschinenbau. Die 60kv Massekabel wurden bis nach Afrika geliefert.</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13769",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/1913_01-300x184.webp"
              }
            ],
            "year": "1913"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Größte Veranstaltung in Schöneweide - Die Beerdigung Emil Rathenaus",
            "body": "<!-- wp:paragraph -->\n<p>Oberschöneweide ist eng verbunden mit dem Namen der Familie Rathenau. Emil Rathenau machte OSW zum Stammsitz der AEG und legte den Grundstein für eines der wichtigsten Industriereviere der damaligen Welt. Mit 76 Jahren starb er an den Folgen seines Diabetes. Sein Sarg wurde im KWO aufgebahrt. Am 20.6.1915 säumten Zehntausende seinen…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13770",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/1915_beerdigung_rathenau-261x300.webp"
              }
            ],
            "year": "1915"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Kriegsverbrecher",
            "body": "<!-- wp:paragraph -->\n<p>Wie alle Großbetriebe in Nazideutschland, so wurde auch die AEG von den Alliierten als „Kriegsverbrecher“ eingestuft. Das Betriebseigentum der AEG im Osten Deutschlands wurde beschlagnahmt und treuhänderisch der Sowjetischen Militäradministration übertragen. Das KWO wurde offiziell aus dem AEG-Konzern herausgelöst und ging als…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13776",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/1946_01-300x215.webp"
              }
            ],
            "year": "1946"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Versammlung auf dem KWO – Gelände am 1. Okt. 1949 - wenige Tage vor der offiziellen Gründung der Deutschen Demokratischen Republik. Alle Betriebe der AEG in der sowjetischen Besatzungszone wurden entschädigungslos enteignet. Da das KWO nun in sowjetischem Besitz war, wurden weitere Demontagen eingestellt. Die sowjetische…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13777",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/1949_01-300x225.webp"
              }
            ],
            "year": "1948"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Volkseigener Betrieb – VEB Kabelwerk Oberspree",
            "body": "<!-- wp:paragraph -->\n<p>Foto: KWO 1951 - Bevor das KWO an die neugegründete DDR übergeben wurde, fotografierten die Sowjets das Werk komplett durch.1952 wurde das KWO der Regierung der DDR als „Volkseigentum“ übergeben und dem Industrieministerium direkt unterstellt. 1952 wurde die Wochenarbeitszeit auf 45 Stunden gesenkt. Unter der Leitung von Generaldirektor…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13778",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/1952_01-300x226.webp"
              }
            ],
            "year": "1952"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Verladung der Kabeltrommeln- KWO um 1961. Das Werk hatte nun 5.000 Beschäftigte. Am Spreeufer wurde die große Fernmeldekabelfabrik wieder neu aufgebaut. Traditionelle Kabelwerkstoffe aus Übersee wie Kupfer, Blei, Kautschuk standen nicht mehr uneingeschränkt zur Verfügung. Um sie durch Aluminium und synthetische Materialien ersetzen…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13779",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/1959_01-300x297.webp"
              }
            ],
            "year": "1959"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Vorzeigebetrieb der DDR",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Flechtmaschinen für Metalldraht, KWO 1966 In den sechziger Jahren wurden ca. 85% der Starkstromkabel der DDR im KWO produziert. Exportiert wurde in mehr als 40 Länder. Erzeugnisse aus dem KWO waren in Kairo und Helsinki ebenso gefragt wie in Moskau und Havanna. Auch für den Ausbau Osteberlins wurden tausende Kilometer Kabel und…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13780",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/1965_01-300x191.webp"
              }
            ],
            "year": "1965"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Kabelpapst, Pionier und Generaldirektor",
            "body": "<!-- wp:paragraph -->\n<p>Foto: Dr. Georg Pohler – Bild aus: Wochenpost, 6.10.1989 Die Entwicklung des KWO ist maßgeblich mit dem Namen Georg Pohler (1913-1997) verbunden. Seine berufliche Laufbahn war außergewöhnlich. 1935 begann er als junger Ingenieur im AEG-Betrieb KWO. 1941 wurde er Entwicklungsleiter in den Draht- Laboratorien und der…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13781",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/1967_01-300x195.webp"
              }
            ],
            "year": "1967"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "Das KWO expandiert",
            "body": "<!-- wp:paragraph -->\n<p>Foto: KWO 1985 – Zentrale Elasteaufbereitung – Fabrikanlage mit Silolager am Spreeufer. Das neue Werk ging 1985 in Betrieb. Es produzierte Elast- und Plastmischungen für die Kabel- und Reifenindustrie. Da es auf dem dicht bebauten KWO-Werksgelände zu wenig Platz für eine große Fabrikanlage gab, musste zunächst Bauland aus der Spree…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13782",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/kwo_1985-300x208.webp"
              }
            ],
            "year": "1985"
          },
          {
            "type": "timeline_item",
            "kicker": "",
            "title": "",
            "body": "<!-- wp:paragraph -->\n<p>1989 gehörten dem Kombinat „VEB KWO“ 13 Betriebe mit rund 17.000 Beschäftigten an. Das Kombinat Kabel deckte bis auf wenige Ausnahmen das gesamte Kabelsortiment ab und hatte eine Warenproduktion von rund drei Milliarden DDR-Mark. Das KWO war „Stammbetrieb“ und galt mit 6.000 Beschäftigten als größtes und traditionsreichstes Kabelwerk der…</p>\n<!-- /wp:paragraph -->",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13783",
                "label": "kabelwerk-oberspree",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/KWO_DDR-300x206.webp"
              }
            ],
            "year": "1989"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 18873,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "13821"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "0"
        ],
        "_iss_publication_price_cents": [
          "0"
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "5210a01d09071ba411b166c71370a9851c51f64ebc5ddd83febd3a363cefdc24",
        "post_content": "b1047c393c630788ce77506848ba9c8c099159005dd89883b97de370282dac5a",
        "post_title": "9de40cae2df96e2725a4bc75fb5d67831600f04d75228b8a17394ca6fbe66b26",
        "post_excerpt": "8c00153e5eaa65a990259bbb097404dbeedd3c1194b5bc78541e74afa9c276ad",
        "post_status": "7743ce348d9284d677a185f33295b92266cc435a5b5f775029b300066d26693a",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "63241c291400c389e2f3ef2d882738915d62157094b1e7b84ec958046252a56f",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "2da014ed7929684373349743ff3e76fe8eb0427cc12e51d0040ea515b340a205"
        ],
        "_wp_page_template": [
          "1080a60800d9e5bddc69f51489c636a776bacaadb5368f46db4aa0947c67e3ba"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_publication_price_cents": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "kinder-im-wf-eine-entwicklungsgeschichte"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p class=\"wp-block-paragraph\">Diese digitale Publikation liest den Korpus zu Kinderbetreuung und Werkalltag im WF als fortlaufenden Text. Die Kapitel darunter folgen derselben Reihenfolge wie die begleitende Ausstellung und können auch einzeln geöffnet werden.</p>"
          },
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Chronik",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 18877,
      "new": false,
      "post": {
        "post_content": "<p>[iss_archive_object id=\"26217\"]</p>\r\n<p>&nbsp;</p>\r\n<!-- wp:paragraph -->\r\n<p>Die Ausstellung bündelt Quellen, Bilder und Einordnungen zur Arbeit, Sichtbarkeit und politischen Rolle von Frauen im Werk für Fernmeldewesen und im WF. Sie öffnet den lokal gesicherten Kapitelkorpus als zusammenhängenden Ausstellungspfad.</p>\r\n<!-- /wp:paragraph -->",
        "post_status": "draft"
      },
      "meta": {
        "_thumbnail_id": [
          "14114"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "1"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "153c826f569a07b889a435d5bcc83b67c10b1e0b507333d6d8d8b486bdc113d8",
        "post_content": "8ea94db1ccd9ce91f6b3adf9ed2155cd48c3d424e04fab4eaf864ce6662066ea",
        "post_title": "51e2658a5162f8af67933a1e975286dc78511a1cf7b9590849d41300b51ab045",
        "post_excerpt": "ed973f5f9d26a5ef0bee13da8a2439b800c3503ac3aea08b5b6d4fe0eb68fe94",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "8c20bf4b4d9c948d8744042f8dddf302651c9ce865de123d8eecf046018db5fc",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "eed5049fe1c57e48bf9875e17205f362645621090f6dc2d8208dc8f2909cbe3a"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "frauen-im-wf"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 18878,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "14114"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "0"
        ],
        "_iss_publication_price_cents": [
          "0"
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "153c826f569a07b889a435d5bcc83b67c10b1e0b507333d6d8d8b486bdc113d8",
        "post_content": "cfa3a3807b7725356361dc972e70f87836c70bc28f07ae6b1ceec8a8e9f385e3",
        "post_title": "905d04da47c10128fd447a12a6ab172b30522f9e2653d266686872dfa3261427",
        "post_excerpt": "d0d7738e8b7ef7df91f42623e30f64e7c3bc7f7e90d16b1cb3fb9f2f58dafb04",
        "post_status": "7743ce348d9284d677a185f33295b92266cc435a5b5f775029b300066d26693a",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "a4c80bdcb8fcc8db6c73eaba26e96410bd3810a1fd80ffdccd2fd1cb11cf1081",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "eed5049fe1c57e48bf9875e17205f362645621090f6dc2d8208dc8f2909cbe3a"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "frauen-im-wf-eine-entwicklungsgeschichte"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "longread-poster",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p>Die Reihe versammelt Quellen, Bilder und Einordnungen zur Arbeit, Sichtbarkeit und politischen Rolle von Frauen im Werk für Fernmeldewesen und im WF. Ausstellung und Publikation greifen auf denselben lokal gesicherten Kapitelkorpus zurück.</p>"
          },
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Lesepfad",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Lesepfad",
            "body": "<p class=\"wp-block-paragraph\">Die begleitende Publikation führt denselben lokal gesicherten Kapitelkorpus als linearen Lesepfad durch die Arbeitswelten, Rollenzuschreibungen und biografischen Spuren von Frauen im WF.</p>",
            "anchor": "lesepfad",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 18880,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "13838"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "fe46a3c2ad8b06e6855c83ccc7a5d80695247002d8cf90e0ea1fc79ea4fc2e7c",
        "post_content": "2e5e6cb871d45d4975c3ce4849382122533b3ab0348c9eecee6ef478aac530bb",
        "post_title": "2dca96c88f7cde2592356846d65aa626cf40c69d5c553fdaebd207d246926498",
        "post_excerpt": "fc0097fa0ced8d2350028be94065828feca5977c6bee2f68f2f0d7c823cedf9a",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "3652fd928cc0d5c3b86137fb217094e16a83576038449c71f5f67cc705c9edb2",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "4cbce5e96d5d41cf5637853fbae4f500415dcef561274f5682f1a8ea204a44d7"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "produktionspropaganda-im-wf-sender"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 18881,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "13838"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          0
        ],
        "_iss_publication_price_cents": [
          0
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "fe46a3c2ad8b06e6855c83ccc7a5d80695247002d8cf90e0ea1fc79ea4fc2e7c",
        "post_content": "b790b166f2aff60f30c742c01faf90586762f7d2a0508b970bac38723ae70d62",
        "post_title": "75de82c2cd3c567736347816c5be1dbb3dc35971241650d941bf8966dfff1abd",
        "post_excerpt": "fc0097fa0ced8d2350028be94065828feca5977c6bee2f68f2f0d7c823cedf9a",
        "post_status": "7743ce348d9284d677a185f33295b92266cc435a5b5f775029b300066d26693a",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "189dd4ea181716b411657f8135c2230ddbac98d85b8adc35adaa156c2626d6fc",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "4cbce5e96d5d41cf5637853fbae4f500415dcef561274f5682f1a8ea204a44d7"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "fundstuecke-aus-dem-wf-sender-eine-quellengeschichte"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "longread-poster",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p>Die dreiteilige Reihe liest Ausgaben des WF-Senders als Quellen zur Kulturpolitik, Kunstausstellungspraxis und Geschichtspolitik im Werk für Fernsehelektronik. Die digitale Ausstellung rekonstruiert die 2023 gezeigte Produktionspropaganda-Schau als lesbaren Quellenpfad.</p>"
          },
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Lesepfad",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Lesepfad",
            "body": "<p class=\"wp-block-paragraph\">Diese Publikation bündelt die drei Folgen von <em>Fundstücke aus dem WF-Sender</em> zu einem linearen Lesepfad. Wo museum-digital-Quellscans vorliegen, bleiben sie im Kapitelkontext direkt verlinkt.</p>",
            "anchor": "lesepfad",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 18885,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "13873"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "096ef5bd64902c9d924990d78ee52268f25e67bd35286f8031e50e21de6b39c3",
        "post_content": "cc64d940806c1c4fc48e4eabcddb2c1f2a23f4404638b748bc1dad1ce3009581",
        "post_title": "27079f3521ca355bcd25d792484bf0d30504ea065e823a70d3cf8cdf01e39091",
        "post_excerpt": "2897e2b4f798f64e0ec93ca5de1fda1d3226fc6bd57c6dbe8c1bc6ca02454bee",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "8c526c475519d297299cfe5013bb417eb04337031a1c1fa443f5fbb04ea696da",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "fa07f95acaac4d6d1887bab5f5351cde091aa1e82ded7f8d654f500c12c04959"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "brigadebuecher-im-industriesalon"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 18886,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "13873"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          0
        ],
        "_iss_publication_price_cents": [
          0
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "096ef5bd64902c9d924990d78ee52268f25e67bd35286f8031e50e21de6b39c3",
        "post_content": "86a3a0d08303f7c46ea51685a135e96f363cf2284ecd4f0025a214c1a7856a87",
        "post_title": "138351a2a6c973e236430014c6f13c69512ee7d5107c942c701997884afa3d12",
        "post_excerpt": "1a67baf7dbffa17ece443f1a72eb67b59694abc043097057c2198e76ea4b0007",
        "post_status": "7743ce348d9284d677a185f33295b92266cc435a5b5f775029b300066d26693a",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "8736aee178bd08f03149f5af102555184420b0a5e905a108a50e8af4adaf73bd",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "fa07f95acaac4d6d1887bab5f5351cde091aa1e82ded7f8d654f500c12c04959"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "brigadebuecher-im-industriesalon-eine-quellengeschichte"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "longread-poster",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p>Die fünfteilige Reihe liest digitalisierte Brigadebücher aus dem Industriesalon als Quellen zu Arbeitsalltag, Kollektivleben und sozialistischer Selbstinszenierung. Ausstellung und Publikation führen denselben gesicherten Quellenkorpus zusammen.</p>"
          },
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Lesepfad",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Lesepfad",
            "body": "<p class=\"wp-block-paragraph\">Diese Publikation bündelt die fünf Teile von <em>Brigadebücher im Industriesalon</em> zu einem linearen Lesepfad. Wo museum-digital-Quellen bereits im Kapitel genannt sind, bleiben sie mit Datensatz, PDF und Vorschauscans direkt im Kapitelkontext sichtbar.</p>",
            "anchor": "lesepfad",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 18894,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "18895"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "0"
        ],
        "_iss_publication_price_cents": [
          "0"
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "4daf230a1800a300b039fef877f0c973161ed6f15236cd94be5cb9de6b6979df",
        "post_content": "2d1dc47ac46f6a9eeef63978fdaa6d146bc9cc1ee701dc7d3feef232267f2632",
        "post_title": "122d7fee2dd1581e26bd802c9588d4c01d9530688c47b3bd4deccc2b1b05401d",
        "post_excerpt": "bbf9c91e54ad02aa18c81e8e70749ef67cc89fd975963ba9e7b34b72a85d3d05",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "5b27d1391266f40b38ab14d84292ce0728550e82c1d2b4040077cef8407f5389",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "e54933d5068b0aaf664dad5d7f7273e6942da3f1149394cce1935d43278ad081"
        ],
        "_wp_page_template": [
          "1080a60800d9e5bddc69f51489c636a776bacaadb5368f46db4aa0947c67e3ba"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_publication_price_cents": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [
          "6b50ea5fd063235b748b616a772b27e296b48eef6252fabf52bc3580c2cbb5b5"
        ],
        "_iss_editorial_enabled_publication": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "fotoalbum-labor-konstruktions-und-versuchswerk-oberspree-1946"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "bildmatrix",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "Fotoalbum",
            "title": "Album und Kontext",
            "body": "Das im August 1945 von den Sowjets gegründete LKVO (Labor-, Konstruktions- und Versuchswerk Oberspree) diente vorwiegend der Vermittlung elektrotechnischen Know-hows und der Nachentwicklung von Elektronenröhren für den sowjetischen Markt. Im Juli 1946 erfolgte die Umbenennung in Oberspreewerk; das Album entstand vermutlich für sowjetische Vorgesetzte im LKVO und dokumentiert den Betrieb in 52 Fotografien."
          },
          {
            "type": "publication_rail",
            "kicker": "Album",
            "title": "Albumnavigation",
            "body": "",
            "rail_options": {
              "show_nav": false,
              "show_summary": false,
              "show_related": false,
              "variant": "detailed"
            }
          },
          {
            "type": "source",
            "kicker": "Quelle",
            "title": "Kontext, Quelle und Rechte",
            "body": "Diese digitale Edition übernimmt die vollständige Bildfolge der bereits vorhandenen WF-Ausgabe. Zur Quellseite: WF-Museum. Ausgangstext zum Kontext: Quellen zur Geschichte des WF – Folge 4.Quelle der Digitaledition: WF-Museum und Industriesalon Schöneweide. Die Bilddateien auf der Quellseite sind mit Industriesalon-Rechten als CC BY-SA ausgewiesen.",
            "links": [
              {
                "label": "WF-Museum",
                "url": "https://wf-museum.de/home-2/betriebsfotoalben/fotoalbum-labor-konstruktions-und-versuchswerk-oberspree-1946/"
              },
              {
                "label": "Quellen zur Geschichte des WF – Folge 4",
                "url": "{{SITE_URL}}/archivbeitraege/quellen-zur-geschichte-des-wf-folge-4-fotoalben-aus-dem-wf/"
              }
            ]
          },
          {
            "type": "photoalbum",
            "kicker": "Album",
            "title": "Fotoalbum Labor, Konstruktions- und Versuchswerk Oberspree, 1946",
            "body": "",
            "album_source": {
              "kind": "manual",
              "set_id": "",
              "set_title": "WF-Museum"
            },
            "sheets": [
              {
                "source_kind": "wp_media",
                "source_id": "18895",
                "visible": true,
                "label": "Blatt 01",
                "nav_title": "Blatt 01",
                "caption": "Titel Labor, Konstruktions- und Versuchswerk Oberspree, S.1 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-1-1-150x150.jpg",
                "position": 1,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18896",
                "visible": true,
                "label": "Blatt 02",
                "nav_title": "Blatt 02",
                "caption": "Hauptgebäude LKB (Labor- und Konstruktions- Büro), S.2 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-2-1-150x150.jpg",
                "position": 2,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18897",
                "visible": true,
                "label": "Blatt 03",
                "nav_title": "Blatt 03",
                "caption": "Neuer Flügel LKB, S.3 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-3-1-150x150.jpg",
                "position": 3,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18898",
                "visible": true,
                "label": "Blatt 04",
                "nav_title": "Blatt 04",
                "caption": "Konstruktionsbüro, S.4 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-4-1-150x150.jpg",
                "position": 4,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18899",
                "visible": true,
                "label": "Blatt 05",
                "nav_title": "Blatt 05",
                "caption": "Vorrichtungswerkstatt, S.5 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-5-1-150x150.jpg",
                "position": 5,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18900",
                "visible": true,
                "label": "Blatt 06",
                "nav_title": "Blatt 06",
                "caption": "Werkstatt-Abteilung, S.6 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-6-1-150x150.jpg",
                "position": 6,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18901",
                "visible": true,
                "label": "Blatt 07",
                "nav_title": "Blatt 07",
                "caption": "Mechanische Werkstatt der Röhrenabteilung, S.7 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-7-1-150x150.jpg",
                "position": 7,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18902",
                "visible": true,
                "label": "Blatt 08",
                "nav_title": "Blatt 08",
                "caption": "Stanzerei, S.8 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-8-1-150x150.jpg",
                "position": 8,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18903",
                "visible": true,
                "label": "Blatt 09",
                "nav_title": "Blatt 09",
                "caption": "Chemisches Laboratorium, S.9 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-9-1-150x150.jpg",
                "position": 9,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18904",
                "visible": true,
                "label": "Blatt 10",
                "nav_title": "Blatt 10",
                "caption": "Maschine zum Wickeln von Gittern, S.10 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-10-1-150x150.jpg",
                "position": 10,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18905",
                "visible": true,
                "label": "Blatt 11",
                "nav_title": "Blatt 11",
                "caption": "Schweißmaschine für die Röhre AS1010, S.11 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946. Der Apparateglasbläser ist gerade beim Verschmelzen der oberen und unteren Glaskolbenhälfte. Hier wurde eine Ringverschmelzung angebracht.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-11-1-150x150.jpg",
                "position": 11,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18906",
                "visible": true,
                "label": "Blatt 12",
                "nav_title": "Blatt 12",
                "caption": "Punktschweißung von Teilen der Röhre AS1010, S.12 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-12-1-150x150.jpg",
                "position": 12,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18907",
                "visible": true,
                "label": "Blatt 13",
                "nav_title": "Blatt 13",
                "caption": "Anlage zur Karbidisierung [?]  von Kathoden der AS1010, S.13 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-13-1-150x150.jpg",
                "position": 13,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18908",
                "visible": true,
                "label": "Blatt 14",
                "nav_title": "Blatt 14",
                "caption": "Station für das Auspumpen der Röhre AS1010, S.14 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-14-1-150x150.jpg",
                "position": 14,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18909",
                "visible": true,
                "label": "Blatt 15",
                "nav_title": "Blatt 15",
                "caption": "Anlage zum […] und Pumpen des Magnetrons 725a, S.15 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-15-1-150x150.jpg",
                "position": 15,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18910",
                "visible": true,
                "label": "Blatt 16",
                "nav_title": "Blatt 16",
                "caption": "Station für die Prüfung von Kathoden der Magnetrons 725a, S.16 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-16-1-150x150.jpg",
                "position": 16,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18911",
                "visible": true,
                "label": "Blatt 17",
                "nav_title": "Blatt 17",
                "caption": "Prüfstand für die Leistungsmessung der Magnetrons 725a, S.17 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-17-1-150x150.jpg",
                "position": 17,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18912",
                "visible": true,
                "label": "Blatt 18",
                "nav_title": "Blatt 18",
                "caption": "Prüfstand für die Sperr-Röhre LG76, S.18 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-18-1-150x150.jpg",
                "position": 18,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18913",
                "visible": true,
                "label": "Blatt 19",
                "nav_title": "Blatt 19",
                "caption": "Abgleichplatz für die Sperr-Röhre LG80, S.19 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-19-1-150x150.jpg",
                "position": 19,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18914",
                "visible": true,
                "label": "Blatt 20",
                "nav_title": "Blatt 20",
                "caption": "Messaufbau für [netz...?] Thermoströme, S.20 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-20-1-150x150.jpg",
                "position": 20,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18915",
                "visible": true,
                "label": "Blatt 21",
                "nav_title": "Blatt 21",
                "caption": "Versuchsplatz für die Röhre 6AC7, S.21 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-21-1-150x150.jpg",
                "position": 21,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18916",
                "visible": true,
                "label": "Blatt 22",
                "nav_title": "Blatt 22",
                "caption": "Konzentrator -Vorrichtung für die Schweißung von Metallröhren, S.22 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-22-1-150x150.jpg",
                "position": 22,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18917",
                "visible": true,
                "label": "Blatt 23",
                "nav_title": "Blatt 23",
                "caption": "Gesamtansicht \"Konzentrator\", S.23 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-23-1-150x150.jpg",
                "position": 23,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18918",
                "visible": true,
                "label": "Blatt 24",
                "nav_title": "Blatt 24",
                "caption": "Anlage zur Erzeugung von Impulsen von 5000 KW Leistung (Gesamtansicht), S.24 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-24-1-150x150.jpg",
                "position": 24,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18919",
                "visible": true,
                "label": "Blatt 25",
                "nav_title": "Blatt 25",
                "caption": "Anlage zur Erzeugung von Impulsen von 5000 KW Leistung (Ansicht von hinten), S.25 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-25-1-150x150.jpg",
                "position": 25,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18920",
                "visible": true,
                "label": "Blatt 26",
                "nav_title": "Blatt 26",
                "caption": "Anlage zur Erzeugung von Impulsen von 5000 KW Leistung (Bedienteil und Stromkaskade),  S.26 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-26-1-150x150.jpg",
                "position": 26,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18921",
                "visible": true,
                "label": "Blatt 27",
                "nav_title": "Blatt 27",
                "caption": "45 KV Gleichrichter für (Training ?) die Röhre LV21, S.27 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-27-1-150x150.jpg",
                "position": 27,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18922",
                "visible": true,
                "label": "Blatt 28",
                "nav_title": "Blatt 28",
                "caption": "Impulsgenerator 200-200 KW für Flugzeugortung (Vorderansicht), S.28 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-28-1-150x150.jpg",
                "position": 28,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18923",
                "visible": true,
                "label": "Blatt 29",
                "nav_title": "Blatt 29",
                "caption": "Impulsgenerator 200-200 KW für Flugzeugortung (Rückansicht), S.29 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-29-1-150x150.jpg",
                "position": 29,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18924",
                "visible": true,
                "label": "Blatt 30",
                "nav_title": "Blatt 30",
                "caption": "Impulsgenerator 5-20 μsec 100-1000 Hz (Gesamtansicht), S.30 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-30-1-150x150.jpg",
                "position": 30,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18925",
                "visible": true,
                "label": "Blatt 31",
                "nav_title": "Blatt 31",
                "caption": "Impulsgenerator 5-20 μsec 100-1000 Hz (Rückansicht), S.31 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-31-1-150x150.jpg",
                "position": 31,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18926",
                "visible": true,
                "label": "Blatt 32",
                "nav_title": "Blatt 32",
                "caption": "Einrichtung für Untersuchung des Wellenbereiches λ = 10 cm, S.32 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-32-1-150x150.jpg",
                "position": 32,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18927",
                "visible": true,
                "label": "Blatt 33",
                "nav_title": "Blatt 33",
                "caption": "Mess-Station \"Aurora\", S.33 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-33-1-150x150.jpg",
                "position": 33,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18928",
                "visible": true,
                "label": "Blatt 34",
                "nav_title": "Blatt 34",
                "caption": "Ofen für die Beschichtung von Detektoren, S.34 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-34-1-150x150.jpg",
                "position": 34,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18929",
                "visible": true,
                "label": "Blatt 35",
                "nav_title": "Blatt 35",
                "caption": "Pumpstand für Thyratrons, S.35 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-35-1-150x150.jpg",
                "position": 35,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18930",
                "visible": true,
                "label": "Blatt 36",
                "nav_title": "Blatt 36",
                "caption": "Erprobungsstation für gasgefüllte Gleichrichter, S.36 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-36-1-150x150.jpg",
                "position": 36,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18931",
                "visible": true,
                "label": "Blatt 37",
                "nav_title": "Blatt 37",
                "caption": "Erprobungsstation für Niedervolt-Thyratrons, S.37 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-37-1-150x150.jpg",
                "position": 37,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18932",
                "visible": true,
                "label": "Blatt 38",
                "nav_title": "Blatt 38",
                "caption": "Hochvolt-Station für Erprobung von Thyratrons, S.38 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-38-1-150x150.jpg",
                "position": 38,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18933",
                "visible": true,
                "label": "Blatt 39",
                "nav_title": "Blatt 39",
                "caption": "Gesamtansicht der Hochvoltstation für die Erprobung von Thyratrons, S.39 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-39-1-150x150.jpg",
                "position": 39,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18934",
                "visible": true,
                "label": "Blatt 40",
                "nav_title": "Blatt 40",
                "caption": "Horizontale Glasknüppel-Anschmelzmaschine lautet die russische Unterschrift, S.40 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946. Es handelt sich um eine horizontale Drehbank zum Verschmelzen von großen runden Glaskolben. Man sieht die Kanten der beiden Kolbenhälften, die gerade durch eine große Lampe erhitzt werden. Dabei dreht sich das Ganze langsam. Sind die Ränder weich, werden die Hälften zusammengebracht und verschmolzen.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-40-1-150x150.jpg",
                "position": 40,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18935",
                "visible": true,
                "label": "Blatt 41",
                "nav_title": "Blatt 41",
                "caption": "Werkbank für Beschichtung und Zwischenprüfung von Kolben für Elektronenstrahlröhren, S.41 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-41-1-150x150.jpg",
                "position": 41,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18936",
                "visible": true,
                "label": "Blatt 42",
                "nav_title": "Blatt 42",
                "caption": "Montagetische für Elektronenstrahlröhren, später handschriftlich hinzugefügt: \"Röhrenaufbau 1946 mit Meister Graczkowski (PG1), S.42 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-42-1-150x150.jpg",
                "position": 42,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18937",
                "visible": true,
                "label": "Blatt 43",
                "nav_title": "Blatt 43",
                "caption": "Punktschweißgerät für Elektronenstrahlröhren, S.43 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-43-1-150x150.jpg",
                "position": 43,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18938",
                "visible": true,
                "label": "Blatt 44",
                "nav_title": "Blatt 44",
                "caption": "Pumpstand für Elektronenstrahlröhren, S.44 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-44-1-150x150.jpg",
                "position": 44,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18939",
                "visible": true,
                "label": "Blatt 45",
                "nav_title": "Blatt 45",
                "caption": "Prüfplatz für zweistrahlige Röhren (HR2/100/1,5A), S.45 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-45-1-150x150.jpg",
                "position": 45,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18940",
                "visible": true,
                "label": "Blatt 46",
                "nav_title": "Blatt 46",
                "caption": "Prüfplatz für einstrahlige Röhren (LB9A), S.46 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-46-1-150x150.jpg",
                "position": 46,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18941",
                "visible": true,
                "label": "Blatt 47",
                "nav_title": "Blatt 47",
                "caption": "Station für das Verschmelzen von Röntgenröhren, S.47 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-47-1-150x150.jpg",
                "position": 47,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18942",
                "visible": true,
                "label": "Blatt 48",
                "nav_title": "Blatt 48",
                "caption": "Pumpstand für Röntgenröhren, S.48 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-48-1-150x150.jpg",
                "position": 48,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18943",
                "visible": true,
                "label": "Blatt 49",
                "nav_title": "Blatt 49",
                "caption": "Pumpstand für Röntgenröhren, S.49 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-49-1-150x150.jpg",
                "position": 49,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18944",
                "visible": true,
                "label": "Blatt 50",
                "nav_title": "Blatt 50",
                "caption": "Gruppe der führenden deutschen Spezialisten im LKVO, S.50 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946. Stehend, v.l.n.r.: Dr. Fogy, Jürgens, Dr. Kaufmann, Grimm, Dr. Fritz, Dr.Hülster, Feußner, Palme, Schiffel, Dr. Bechmann, Dr. Hagen, Kettel, unbekannt. Sitzend: Dr. Roosenstein, Herzog, Dr. Granitza, Dr. Steimel, Spiegel, Gruner, Dr. Richter, Dr. Kotowski. Die Spezialisten sollen am 22.10. 1946 in die UdSSR ausgereist sein (Quelle: letzter Personalleiter von Telefunken). Das im August 1945 von den Sowjets gegründete LKVO (Labor-, Konstruktions- und Versuchswerk Oberspree) diente vorwiegend der Vermittlung elektrotechnischen Know-Hows und der Nachentwicklung von Elektronenröhren für den sowjetischen Markt. Im Juli 1946 erfolgte die Umbenennung des LKVO in Oberspreewerk und es erhielt die Rechtsform einer SAG. Das Album entstand vermutlich für den sowjetischen Vorgesetzten im LKVO.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-50-1-150x150.jpg",
                "position": 50,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18945",
                "visible": true,
                "label": "Blatt 51",
                "nav_title": "Blatt 51",
                "caption": "Materiallager, S.51 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-51-1-150x150.jpg",
                "position": 51,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18946",
                "visible": true,
                "label": "Blatt 52",
                "nav_title": "Blatt 52",
                "caption": "Rohstofflager, S.52 des Fotoalbums \"Labor, Konstruktions- und Versuchswerk Oberspree\" mit 52 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-001-52-1-150x150.jpg",
                "position": 52,
                "source_item_id": "0"
              }
            ]
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 18948,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "18949"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          0
        ],
        "_iss_publication_price_cents": [
          0
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "fa6f3277a9daeed20198bb0e58650dab8ee528f711e95f7c2f22d89cf21e32b9",
        "post_content": "80e1ccddbab32b2d3eca52ac2f8dfd294ecb98a16ee5d18476513aae35717f93",
        "post_title": "7b3cc5992b6eca023757dac30412d3f5de59bcd7a92306f93f735ff21dd9b0d2",
        "post_excerpt": "61533fa66e018637259a4ab9d3e77ae3af298c7eb2d0029304df054a91bf7acb",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "0ede6d22981ac45d49709ab0acd5193bdf34d0a314b99d7832d8d65466efd140",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "d0fa600d83fc7e038287cb3d461b7676e69566fde00ae227b50692c844fe5ed6"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [
          "70477f7cfb7f1e1f40068cec1bbcc155a19162905ad404c301ac93a96001f728"
        ],
        "_iss_editorial_enabled_publication": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "fotoalbum-produkte-lkvo-1946"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "bildmatrix",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "Fotoalbum",
            "title": "Album und Kontext",
            "body": "Das im August 1945 von den Sowjets gegründete LKVO (Labor-, Konstruktions- und Versuchswerk Oberspree) diente vorwiegend der Vermittlung elektrotechnischen Know-Hows und der Nachentwicklung von Elektronenröhren für den sowjetischen Markt. Im Juli 1946 erfolgte die Umbenennung des LKVO in Oberspreewerk und es erhielt die Rechtsform einer SAG. Das Fotoalbum mit den Produkten des LKVO entstand vermutlich für den sowjetischen Vorgesetzten."
          },
          {
            "type": "publication_rail",
            "kicker": "Album",
            "title": "Albumnavigation",
            "body": "",
            "rail_options": {
              "show_nav": false,
              "show_summary": false,
              "show_related": false,
              "variant": "detailed"
            }
          },
          {
            "type": "source",
            "kicker": "Quelle",
            "title": "Kontext, Quelle und Rechte",
            "body": "Kontext und Quelle: WF-Museum: Fotoalbum Produkte LKVO 1946 und Quellen zur Geschichte des WF - Folge 4 - Fotoalben aus dem WF.Quelle und Rechte: WF-Museum / Industriesalon Schoneweide, CC BY-SA.",
            "links": [
              {
                "label": "WF-Museum: Fotoalbum Produkte LKVO 1946",
                "url": "https://wf-museum.de/home-2/betriebsfotoalben/fotoalbum-produkte-lkvo-1946/"
              },
              {
                "label": "Quellen zur Geschichte des WF - Folge 4 - Fotoalben aus dem WF",
                "url": "{{SITE_URL}}/archivbeitraege/quellen-zur-geschichte-des-wf-folge-4-fotoalben-aus-dem-wf/"
              }
            ]
          },
          {
            "type": "photoalbum",
            "kicker": "Album",
            "title": "Fotoalbum Produkte LKVO 1946",
            "body": "",
            "album_source": {
              "kind": "manual",
              "set_id": "",
              "set_title": "WF-Museum"
            },
            "sheets": [
              {
                "source_kind": "wp_media",
                "source_id": "18949",
                "visible": true,
                "label": "Blatt 01",
                "nav_title": "Blatt 01",
                "caption": "Titel: Labor- Konstruktions- und Versuchswerk Oberspree N.K.E.P, Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-24-1-150x150.jpg",
                "position": 1,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18950",
                "visible": true,
                "label": "Blatt 02",
                "nav_title": "Blatt 02",
                "caption": "Reflexklystron LD20 (λ = 3 cm), Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-2-1-150x150.jpg",
                "position": 2,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18951",
                "visible": true,
                "label": "Blatt 03",
                "nav_title": "Blatt 03",
                "caption": "Einzelteile des Reflexklystrons LD20 (λ = 3 cm), Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-3-1-150x150.jpg",
                "position": 3,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18952",
                "visible": true,
                "label": "Blatt 04",
                "nav_title": "Blatt 04",
                "caption": "Hochvolt Oszillografenröhren, links 4kV (vorn E210, hinten 2068), rechts 25kV (vorn E115, hinten 2066), Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-4-1-150x150.jpg",
                "position": 4,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18953",
                "visible": true,
                "label": "Blatt 05",
                "nav_title": "Blatt 05",
                "caption": "Nachentwicklungen von TR tubes für RADAR (Bezeichnung der Alliierten) und Nulloden für Funkmessgeräte (Bezeichnung der deutschen Wehrmacht) mit Maßstab zum Größenvergleich. v.l.n.r.Nachentwicklung einer amerikanischen oder britischen Sperrröhre (TR tube) ähnlich 1B24(A) mit Hilfsentladungsstrecke. Ein Abschnitt des Hohlleiters ist Teil der Röhre.Nachentwicklung der LG75, Telefunken, Nullode der Wehrmacht mit Hilfsentladungsstrecke. Die beiden konzentrischen Kupferscheiben bildeten den Abschluss zum Resonator.Nachentwicklung der LG76, Telefunken, Nullode der Wehrmacht. Konzentrischer Glaskolben ohne Elektroden, der in einen Koaxleiter eingeschoben wurde. Die Außenseite ist geschoopt (durch Metallspritzverfahren mit Zink geschichtet).Aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-6-1-150x150.jpg",
                "position": 5,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18954",
                "visible": true,
                "label": "Blatt 06",
                "nav_title": "Blatt 06",
                "caption": "Lager von Röntgenröhren, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-8-2-150x150.jpg",
                "position": 6,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18955",
                "visible": true,
                "label": "Blatt 07",
                "nav_title": "Blatt 07",
                "caption": "Messgenerator für Röhren 6J6 (λ = 70 cm) mit Maßstab als Größenvergleich, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-9-1-150x150.jpg",
                "position": 7,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18956",
                "visible": true,
                "label": "Blatt 08",
                "nav_title": "Blatt 08",
                "caption": "Messgenerator für Röhren 829 (λ = 1-2 m) mit Maßstab als Größenvergleich, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-10-1-150x150.jpg",
                "position": 8,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18957",
                "visible": true,
                "label": "Blatt 09",
                "nav_title": "Blatt 09",
                "caption": "Vorrichtung für die Leistungsmessung bei λ = 3 cm, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-12-1-150x150.jpg",
                "position": 9,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18958",
                "visible": true,
                "label": "Blatt 10",
                "nav_title": "Blatt 10",
                "caption": "3 cm-Wellen-Messapparat für Leistungsmessung mit Bolometer, zusammengebaut, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-13-1-150x150.jpg",
                "position": 10,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18959",
                "visible": true,
                "label": "Blatt 11",
                "nav_title": "Blatt 11",
                "caption": "3 cm-Wellen-Messapparat für Leistungsmessung mit Bolometer, zerlegt, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-14-1-150x150.jpg",
                "position": 11,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18960",
                "visible": true,
                "label": "Blatt 12",
                "nav_title": "Blatt 12",
                "caption": "Messtrecke λ = 9 cm (Wellenleitung), Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-15-1-150x150.jpg",
                "position": 12,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18961",
                "visible": true,
                "label": "Blatt 13",
                "nav_title": "Blatt 13",
                "caption": "Konzentrische Strecke für Leistungsmessung bei λ = 9 cm (für das Klystron 726A), Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-16-1-150x150.jpg",
                "position": 13,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18962",
                "visible": true,
                "label": "Blatt 14",
                "nav_title": "Blatt 14",
                "caption": "Metallkeramische Triode LD9 (λ = 15cm), Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-1-1-150x150.jpg",
                "position": 14,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18963",
                "visible": true,
                "label": "Blatt 15",
                "nav_title": "Blatt 15",
                "caption": "Mess-Strecke für λ = 10 cm, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-17-1-150x150.jpg",
                "position": 15,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18964",
                "visible": true,
                "label": "Blatt 16",
                "nav_title": "Blatt 16",
                "caption": "Wellenmesser 10 cm - Gesamtansicht mit einer Hand als Größenvergleich, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-18-1-150x150.jpg",
                "position": 16,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18965",
                "visible": true,
                "label": "Blatt 17",
                "nav_title": "Blatt 17",
                "caption": "Thyratrons: Glas-Typen S15/150i, Metall-Typen LG1001, Gruppenfoto mit kompletten Röhren und mit Einzelteilen, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-7-1-150x150.jpg",
                "position": 17,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18966",
                "visible": true,
                "label": "Blatt 18",
                "nav_title": "Blatt 18",
                "caption": "Einzelteile des Wellenmessers 10 cm, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-19-1-150x150.jpg",
                "position": 18,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18967",
                "visible": true,
                "label": "Blatt 19",
                "nav_title": "Blatt 19",
                "caption": "Kalorimetrische Methode der Leistungsmessung, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-11-1-150x150.jpg",
                "position": 19,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18968",
                "visible": true,
                "label": "Blatt 20",
                "nav_title": "Blatt 20",
                "caption": "Konzentrische Mess-Strecke λ = 10 cm, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-20-1-150x150.jpg",
                "position": 20,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18969",
                "visible": true,
                "label": "Blatt 21",
                "nav_title": "Blatt 21",
                "caption": "Vorrichtung für die Abstimmung der Resonator Entladung (=Sperröhre?) LG76 mit Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-21-1-150x150.jpg",
                "position": 21,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18970",
                "visible": true,
                "label": "Blatt 22",
                "nav_title": "Blatt 22",
                "caption": "Montage einer Breitbandantenne für Feldmessungen, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-22-1-150x150.jpg",
                "position": 22,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "18971",
                "visible": true,
                "label": "Blatt 23",
                "nav_title": "Blatt 23",
                "caption": "Breitbandantenne für Feldmessungen, Foto aus dem Fotoalbum \"Messeinrichtungen und entwickelte und produzierte Spezialröhren L.K.B\" mit 23 Fotos mit russischer Beschriftung, Frühjahr 1946.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-002-23-1-150x150.jpg",
                "position": 23,
                "source_item_id": "0"
              }
            ]
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 18973,
      "new": false,
      "post": {
        "post_content": "<!-- wp:paragraph -->\n<p>Nach Kriegsende wurde das das 6. WTB (wissenschaftlich-technische Buro) als „Nachrichtentechnik-Entwicklung und Fabrikation“, kurz NEF, von der sowjetischen Verwaltung in Ostberlin in den Raumen des ehemaligen zu AEG gehorenden „Fernmeldekabel- und Apparatefabrik Oberspree“ (FAO) im Bauteil B des Peter-Behrens-Baus in Oberschoneweide eingerichtet. Das NEF befasste sich wie das FAO zuvor mit der Entwicklung und Musterfertigung von Tragerfrequenzeinrichtungen, Fernsteuereinrichtungen, Fernschreibeinrichtungen und den dazugehorigen Messgeraten. Dieser Betrieb unterstand dem sowjetischen Ministerium fur Kommunikation. Zum 1. Januar 1950 wurde das NEF mit dem Vorgangerbetrieb des Werks fur Fernmeldewesen HF (spater Werk fur Fernsehelektronik WF), dem OSW (Oberspreewerk), fusioniert. Das Fotoalbum entstand vermutlich fur den sowjetischen Vorgesetzten im NEF.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Kontext und Quelle: <a href=\"https://wf-museum.de/home-2/betriebsfotoalben/nef-album/\">WF-Museum: NEF-Album</a>, <a href=\"{{SITE_URL}}/archivbeitraege/quellen-zur-geschichte-des-wf-folge-4-fotoalben-aus-dem-wf/\">Quellen zur Geschichte des WF - Folge 4 - Fotoalben aus dem WF</a> und <a href=\"{{SITE_URL}}/archivbeitraege/fundstuecke-zur-geschichte-des-nef-im-archiv-des-industriesalons/\">Fundstucke zur Geschichte des NEF im Archiv des Industriesalons</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Quelle und Rechte: WF-Museum / Industriesalon Schoneweide, CC BY-SA.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Albumseiten und Details</h2>\n<!-- /wp:heading -->\n\n<!-- wp:image {\"id\":18974,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_01-1.jpg\" alt=\"Einband des Fotoalbums des NEF mit gravierter Metallplatte, auf der der Titel das Fotoalbums steht: &quot;MEP-SSSR /Technisches Büro für Leitungsverbindungen/ NEF / 1946 / Berlin&quot;. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18974\"/><figcaption class=\"wp-element-caption\">Einband des Fotoalbums des NEF mit gravierter Metallplatte, auf der der Titel das Fotoalbums steht: \"MEP-SSSR /Technisches Büro für Leitungsverbindungen/ NEF / 1946 / Berlin\". Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18975,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_03-1.jpg\" alt=\"Informationstext über das NEF auf kyrillisch. Die Übersetzung lautet: In dem auf dem Gelände des Werkes FAO AEG gelegenen Büro, das sich in Berlin Oberschöneweide, Ostendstraße 1-5 befindet, werden Apparaturen aller Art hergestellt.Vor dem Büro liegt die Aufgabe, neue Arten von Geräten für Leitungsverbindungen zu bearbeiten, Muster zu konstruieren und herzustellen, den technologischen Prozess vorzubereiten, spezielle Ausrüstungen für sowjetische Betriebe zu konstruieren und herzustellen und mit besonderen Hilfsmitteln Vorseriengeräte herzustellen .Die Hauptarbeit des Technischen Büros besteht in der Ausarbeitung neuer Arten von Geräten für Fernverbindungen – als universelle Systeme. Die Idee eines universellen Systems, das während des Krieges von deutschen Spezialisten vorgeschlagen wurde, besteht darin, Grundbausteine für die Kombination verschiedener Kommunikationswege zu schaffen, standardisierte Blöcke, die unterschiedliche Verbindungslinien wie etwa: Freileitungen, Kabellinien, Koaxialkabel und Verbindungen über Dezimeterwellen an das Fernmeldesystem anpassen. Das universelle System ersetzt die verschiedenen vorhandenen Arten von Fernmeldegeräten.Die Vereinheitlichung der Geräte vereinfacht außerordentlich ihre Produktion und Nutzung. Damit bringen universelle Systeme zweifellos ein Interesse zur Angleichung der Schaltungen und Konstruktionen mit sich, insbesondere der Konstruktion besonderer Halbfabrikate.Die Arbeiten im Bereich der universellen Systeme werden auf die Entwicklung von Grundlagen ausgerichtet, sie führen zur Ausarbeitung prinzipieller Schaltungen, Konstruktionen, technologischer Prozesse und Werkzeugbau.Das Technische Büro leistet wesentliche Hilfe für sowjetische Werke bei der Entwicklung neuer Systeme der automatischen Telefonvermittlung des Typs „F“ und für Telefonapparate der Firma Siemens und Halske. Auf diesem Arbeitsgebiet beschränkt sich die Arbeit auf das Sammeln der erforderlichen technischen Dokumentat\" class=\"wp-image-18975\"/><figcaption class=\"wp-element-caption\">Informationstext über das NEF auf kyrillisch. Die Übersetzung lautet: In dem auf dem Gelände des Werkes FAO AEG gelegenen Büro, das sich in Berlin Oberschöneweide, Ostendstraße 1-5 befindet, werden Apparaturen aller Art hergestellt.Vor dem Büro liegt die Aufgabe, neue Arten von Geräten für Leitungsverbindungen zu bearbeiten, Muster zu konstruieren und herzustellen, den technologischen Prozess vorzubereiten, spezielle Ausrüstungen für sowjetische Betriebe zu konstruieren und herzustellen und mit besonderen Hilfsmitteln Vorseriengeräte herzustellen .Die Hauptarbeit des Technischen Büros besteht in der Ausarbeitung neuer Arten von Geräten für Fernverbindungen – als universelle Systeme. Die Idee eines universellen Systems, das während des Krieges von deutschen Spezialisten vorgeschlagen wurde, besteht darin, Grundbausteine für die Kombination verschiedener Kommunikationswege zu schaffen, standardisierte Blöcke, die unterschiedliche Verbindungslinien wie etwa: Freileitungen, Kabellinien, Koaxialkabel und Verbindungen über Dezimeterwellen an das Fernmeldesystem anpassen. Das universelle System ersetzt die verschiedenen vorhandenen Arten von Fernmeldegeräten.Die Vereinheitlichung der Geräte vereinfacht außerordentlich ihre Produktion und Nutzung. Damit bringen universelle Systeme zweifellos ein Interesse zur Angleichung der Schaltungen und Konstruktionen mit sich, insbesondere der Konstruktion besonderer Halbfabrikate.Die Arbeiten im Bereich der universellen Systeme werden auf die Entwicklung von Grundlagen ausgerichtet, sie führen zur Ausarbeitung prinzipieller Schaltungen, Konstruktionen, technologischer Prozesse und Werkzeugbau.Das Technische Büro leistet wesentliche Hilfe für sowjetische Werke bei der Entwicklung neuer Systeme der automatischen Telefonvermittlung des Typs „F“ und für Telefonapparate der Firma Siemens und Halske. Auf diesem Arbeitsgebiet beschränkt sich die Arbeit auf das Sammeln der erforderlichen technischen Dokumentat</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18976,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_05-1.jpg\" alt=\"Organisationsdiagramm des NEF, Stand: 1. Juni 1946. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18976\"/><figcaption class=\"wp-element-caption\">Organisationsdiagramm des NEF, Stand: 1. Juni 1946. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18977,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_07-2.jpg\" alt=\"Gesamtansicht der S.7 im Fotoalbum des NEF. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.t.\" class=\"wp-image-18977\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S.7 im Fotoalbum des NEF. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.t.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18978,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_07-1-1.jpg\" alt=\"Ansicht der Peter-Behrens-Baus mit Turm im Frühjahr 1946. Übersetzung der Bildunterschrift: &quot;Gebäude, in dem sich das Technische Büro befindet.&quot; Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18978\"/><figcaption class=\"wp-element-caption\">Ansicht der Peter-Behrens-Baus mit Turm im Frühjahr 1946. Übersetzung der Bildunterschrift: \"Gebäude, in dem sich das Technische Büro befindet.\" Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18979,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_11-2.jpg\" alt=\"Gesamtansicht der S.11 im Fotoalbum des NEF. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.igt.\" class=\"wp-image-18979\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S.11 im Fotoalbum des NEF. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.igt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18980,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_11-1-1.jpg\" alt=\"Leitende Angestellte des NEF. Übersetzung der Bildunterschrift: &quot;Leitende Spezialisten bei einer Abteilungsbesprechung / Stehend von links nach rechts: 1. Dipl.Ing. Kleinschnitz / Hochfrequenzsysteme 2. Dr. Burghardt / (?) 3. Dipl.Ing. Schneider / elektrische Filter 4. Dipl.Ing. Pässler / Telefonie über HF Leitungen / 5. Dipl.Ing. Mählis / Telegrafen Endgeräte 6. Dipl.Ing. Flurl / Material und Halbzeuge 7. Dipl.Ing. Gerlach / Elektroakustik / 8. Dipl.Ing. Seidel / Messtechnik 9. Dipl.Ing. Grieger / Verstärker 10.Dipl.Ing. Oehlen / Netzprojektierung Fernverbindung.Sitzend von links nach rechts: 1. Dr. Weinitschke / Projektierung Fernlinien 2. Dr. Gessler / Werkstatt Universalsysteme 3. Dr. Kluge / Werkstatt Sonderfertigung 4. Dr. Thierbach / Weitverkehrssysteme 5. Ing. Roloff/ Techn.-Ökonom. Projektierung Weitverkehrsnetze 6. Dipl.Ing. Domsch / Technische Information.&quot; Ausschnitt aus S. 11 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18980\"/><figcaption class=\"wp-element-caption\">Leitende Angestellte des NEF. Übersetzung der Bildunterschrift: \"Leitende Spezialisten bei einer Abteilungsbesprechung / Stehend von links nach rechts: 1. Dipl.Ing. Kleinschnitz / Hochfrequenzsysteme 2. Dr. Burghardt / (?) 3. Dipl.Ing. Schneider / elektrische Filter 4. Dipl.Ing. Pässler / Telefonie über HF Leitungen / 5. Dipl.Ing. Mählis / Telegrafen Endgeräte 6. Dipl.Ing. Flurl / Material und Halbzeuge 7. Dipl.Ing. Gerlach / Elektroakustik / 8. Dipl.Ing. Seidel / Messtechnik 9. Dipl.Ing. Grieger / Verstärker 10.Dipl.Ing. Oehlen / Netzprojektierung Fernverbindung.Sitzend von links nach rechts: 1. Dr. Weinitschke / Projektierung Fernlinien 2. Dr. Gessler / Werkstatt Universalsysteme 3. Dr. Kluge / Werkstatt Sonderfertigung 4. Dr. Thierbach / Weitverkehrssysteme 5. Ing. Roloff/ Techn.-Ökonom. Projektierung Weitverkehrsnetze 6. Dipl.Ing. Domsch / Technische Information.\" Ausschnitt aus S. 11 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18981,\"width\":\"960px\",\"height\":\"auto\",\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full is-resized\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_13-3.jpg\" alt=\"Fotos Juni 1946. \" class=\"wp-image-18981\" style=\"width:960px;height:auto\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S.13 im Fotoalbum des NEF, Fotos Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18982,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_13-1-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Geräte für Fernverbindungen ME, die vom Werk FAO-AEG hergestellt wurden, werden für Laborexperimente genutzt.&quot; Ausschnitt aus S. 13 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18982\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Geräte für Fernverbindungen ME, die vom Werk FAO-AEG hergestellt wurden, werden für Laborexperimente genutzt.\" Ausschnitt aus S. 13 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18983,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_13-2-2.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Telefonapparatur für Linien hoher Leistung, hergestellt vom Werk FAO-AEG wird für Laborexperimente genutzt.&quot; Ausschnitt aus S. 13 des Fotoalbum NEF. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18983\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Telefonapparatur für Linien hoher Leistung, hergestellt vom Werk FAO-AEG wird für Laborexperimente genutzt.\" Ausschnitt aus S. 13 des Fotoalbum NEF. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18984,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_15-3.jpg\" alt=\"Gesamtansicht der S.15 im Fotoalbum des NEF, Fotos Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18984\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S.15 im Fotoalbum des NEF, Fotos Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18985,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_15-1-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Platz für die Aufzeichnung der Frequenzcharakteristik.&quot; Ausschnitt aus S. 15 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18985\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Platz für die Aufzeichnung der Frequenzcharakteristik.\" Ausschnitt aus S. 15 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18986,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_15-2-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Arbeitsplatz für die Prüfung von Halbfabrikaten.&quot; Ausschnitt aus S. 15 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18986\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Arbeitsplatz für die Prüfung von Halbfabrikaten.\" Ausschnitt aus S. 15 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18987,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_17-3.jpg\" alt=\"Gesamtansicht der S.17 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18987\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S.17 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18988,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_17-1-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Konstruktionsbüro der Abteilung bei der Arbeit.&quot; Ausschnitt aus S. 17 des NEF-Fotoalbum. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18988\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Konstruktionsbüro der Abteilung bei der Arbeit.\" Ausschnitt aus S. 17 des NEF-Fotoalbum. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18989,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_17-2-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Arbeitsplatz für die Prüfung von Filtern&quot;. Ausschnitt aus S. 17 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18989\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Arbeitsplatz für die Prüfung von Filtern\". Ausschnitt aus S. 17 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18990,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_19-3.jpg\" alt=\"Gesamtansicht der S.19 im Fotoalbum des NEF. Bildunterschrift: &quot;Maschinenraum, der für die Versorgung der Laboratorien genutzt wird.&quot; Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18990\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S.19 im Fotoalbum des NEF. Bildunterschrift: \"Maschinenraum, der für die Versorgung der Laboratorien genutzt wird.\" Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18991,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_19-1-1.jpg\" alt=\"Blick auf die Fensterseite des Maschinenraums. Ausschnitt aus S. 19 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18991\"/><figcaption class=\"wp-element-caption\">Blick auf die Fensterseite des Maschinenraums. Ausschnitt aus S. 19 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18992,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_19-2-1.jpg\" alt=\"Blick auf die dem Fenster gegenüberliegende Seite des Maschinenraums. Ausschnitt aus S. 19 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18992\"/><figcaption class=\"wp-element-caption\">Blick auf die dem Fenster gegenüberliegende Seite des Maschinenraums. Ausschnitt aus S. 19 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18993,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_21-2.jpg\" alt=\"Gesamtansicht der S. 21 im Fotoalbum des NEF. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18993\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 21 im Fotoalbum des NEF. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18994,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_21-1-1.jpg\" alt=\"Ansicht von Baugruppen. Übersetzung der Bildunterschrift: &quot;Baugruppen, die in universellen Systemen arbeiten.&quot; Ausschnitt aus S. 21 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18994\"/><figcaption class=\"wp-element-caption\">Ansicht von Baugruppen. Übersetzung der Bildunterschrift: \"Baugruppen, die in universellen Systemen arbeiten.\" Ausschnitt aus S. 21 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18995,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_25-3.jpg\" alt=\"Gesamtansicht der S. 25 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18995\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 25 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18996,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_25-1-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Werkstattbereich der Versuchsabteilung.&quot; Ausschnitt aus S. 25 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18996\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Werkstattbereich der Versuchsabteilung.\" Ausschnitt aus S. 25 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18997,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_25-2-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Bereich der Versuchsabteilung beim Zusammenbau von Geräten.&quot; Ausschnitt aus S. 25 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18997\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Bereich der Versuchsabteilung beim Zusammenbau von Geräten.\" Ausschnitt aus S. 25 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18998,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_27-3.jpg\" alt=\"Gesamtansicht der S. 27 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18998\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 27 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":18999,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_27-1-1.jpg\" alt=\"Wickelei der Versuchswerkstatt. Übersetzung der Bildunterschrift: &quot;Wickeln von Netztransformatoren.&quot; Ausschnitt aus S. 27 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-18999\"/><figcaption class=\"wp-element-caption\">Wickelei der Versuchswerkstatt. Übersetzung der Bildunterschrift: \"Wickeln von Netztransformatoren.\" Ausschnitt aus S. 27 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19000,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_27-2-1.jpg\" alt=\"Wickelei der Versuchswerkstatt. Übersetzung der Bildunterschrift: &quot;Wicklerei der Versuchswerkstatt, rechts: Bänke für das Wickeln von Ringkernen.&quot; Ausschnitt aus S. 27 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19000\"/><figcaption class=\"wp-element-caption\">Wickelei der Versuchswerkstatt. Übersetzung der Bildunterschrift: \"Wicklerei der Versuchswerkstatt, rechts: Bänke für das Wickeln von Ringkernen.\" Ausschnitt aus S. 27 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19001,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_29-3.jpg\" alt=\"Gesamtansicht der S. 29 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19001\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 29 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19002,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_29-1-1.jpg\" alt=\"Konstrukteure beim Anfertigen technischer Zeichnungen. Übersetzung der Bildunterschrift: &quot;Konstruktionsbüro für Sondergeräte.&quot; Ausschnitt aus S. 29 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19002\"/><figcaption class=\"wp-element-caption\">Konstrukteure beim Anfertigen technischer Zeichnungen. Übersetzung der Bildunterschrift: \"Konstruktionsbüro für Sondergeräte.\" Ausschnitt aus S. 29 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19003,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_29-2-1.jpg\" alt=\"Büro für technische Information. Übersetzung der Bildunterschrift: &quot;Technologisches Büro.&quot; Ausschnitt aus S. 29 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19003\"/><figcaption class=\"wp-element-caption\">Büro für technische Information. Übersetzung der Bildunterschrift: \"Technologisches Büro.\" Ausschnitt aus S. 29 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19004,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_31-2.jpg\" alt=\"Gesamtansicht der S. 31 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19004\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 31 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19005,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_31-1-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Schlosserwerkstatt der Geräteabteilung /im Vordergrund/.&quot; Ausschnitt aus S. 31 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19005\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Schlosserwerkstatt der Geräteabteilung /im Vordergrund/.\" Ausschnitt aus S. 31 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19006,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_33-3.jpg\" alt=\"Gesamtansicht der S. 33 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19006\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 33 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19007,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_33-1-1.jpg\" alt=\"Schleifmaschinen im Einsatz. Übersetzung der Bildunterschrift: &quot;Gruppe von Schleifarbeitsplätzen der Geräteabteilung&quot;. Ausschnitt aus S. 33 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19007\"/><figcaption class=\"wp-element-caption\">Schleifmaschinen im Einsatz. Übersetzung der Bildunterschrift: \"Gruppe von Schleifarbeitsplätzen der Geräteabteilung\". Ausschnitt aus S. 33 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19008,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_33-2-1.jpg\" alt=\"Fräsmaschinen im Einsatz. Übersetzung der Bildunterschrift: &quot;Gruppe von Fräsarbeitsplätzen der Geräteabteilung&quot;. Ausschnitt aus S. 33 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19008\"/><figcaption class=\"wp-element-caption\">Fräsmaschinen im Einsatz. Übersetzung der Bildunterschrift: \"Gruppe von Fräsarbeitsplätzen der Geräteabteilung\". Ausschnitt aus S. 33 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19009,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_35-3.jpg\" alt=\"Gesamtansicht der S. 35 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19009\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 35 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19010,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_35-1-1.jpg\" alt=\"Drehbänke und Hobelmaschinen im Einsatz. Übersetzung der Bildunterschrift: &quot;Gruppe von Drehbänken und Hobelmaschinen im Werkzeugbau.&quot; Ausschnitt aus S. 35 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19010\"/><figcaption class=\"wp-element-caption\">Drehbänke und Hobelmaschinen im Einsatz. Übersetzung der Bildunterschrift: \"Gruppe von Drehbänken und Hobelmaschinen im Werkzeugbau.\" Ausschnitt aus S. 35 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19011,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_35-2-1.jpg\" alt=\"Drehbänke im Einsatz. Übersetzung der Bildunterschrift: &quot;Gruppe von Drehbänken der Geräteabteilung.&quot; Ausschnitt aus S. 35 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19011\"/><figcaption class=\"wp-element-caption\">Drehbänke im Einsatz. Übersetzung der Bildunterschrift: \"Gruppe von Drehbänken der Geräteabteilung.\" Ausschnitt aus S. 35 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19012,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_37-3.jpg\" alt=\"Gesamtansicht der S. 37 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19012\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 37 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19013,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_37-1-1.jpg\" alt=\"Präzisions-Universalbohrer. Übersetzung der Bildunterschrift: &quot;Präzisions-Universalbohrer Arbeitsplatz&quot;. Ausschnitt aus S. 37 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19013\"/><figcaption class=\"wp-element-caption\">Präzisions-Universalbohrer. Übersetzung der Bildunterschrift: \"Präzisions-Universalbohrer Arbeitsplatz\". Ausschnitt aus S. 37 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19014,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_37-2-1.jpg\" alt=\"Hobelmaschine. Übersetzung der Bildunterschrift: &quot;Hobelmaschinen Arbeitsplatz&quot;. Ausschnitt aus S. 37 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19014\"/><figcaption class=\"wp-element-caption\">Hobelmaschine. Übersetzung der Bildunterschrift: \"Hobelmaschinen Arbeitsplatz\". Ausschnitt aus S. 37 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19015,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_39-3.jpg\" alt=\"Gesamtansicht der S. 39 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19015\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 39 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19016,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_39-1-1.jpg\" alt=\"Universal-Fräsmaschine im Einsatz. Übersetzung der Bildunterschrift: &quot;Universal-Fräsmaschinen Arbeitsplatz der Geräteabteilung&quot;. Ausschnitt aus S. 39 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19016\"/><figcaption class=\"wp-element-caption\">Universal-Fräsmaschine im Einsatz. Übersetzung der Bildunterschrift: \"Universal-Fräsmaschinen Arbeitsplatz der Geräteabteilung\". Ausschnitt aus S. 39 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19017,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_39-2-1.jpg\" alt=\"Vertikal-Fräsmaschine im Einsatz. Übersetzung der Bildunterschrift: &quot;Vertikal-Fräsmaschinen Arbeitsplatz der Geräteabteilung&quot;. Ausschnitt aus S. 39 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19017\"/><figcaption class=\"wp-element-caption\">Vertikal-Fräsmaschine im Einsatz. Übersetzung der Bildunterschrift: \"Vertikal-Fräsmaschinen Arbeitsplatz der Geräteabteilung\". Ausschnitt aus S. 39 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19018,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_41-2.jpg\" alt=\"Gesamtansicht der S. 41 im Fotoalbum des NEF. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19018\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 41 im Fotoalbum des NEF. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19019,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_41-1-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Garnitur von Werkzeugen und Vorrichtungen für das Flachrelais 70, die in der Werkstatt gefertigt wurden&quot;. Ausschnitt aus S. 41 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19019\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Garnitur von Werkzeugen und Vorrichtungen für das Flachrelais 70, die in der Werkstatt gefertigt wurden\". Ausschnitt aus S. 41 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19020,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_45-4.jpg\" alt=\"Gesamtansicht der S. 45 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19020\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 45 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19021,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_45-1-1.jpg\" alt=\"Frau an einem Vervielfältgungsapparat. Übersetzung der Bildunterschrift: &quot;Vervielfältigungsapparate&quot;. Ausschnitt aus S. 45 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19021\"/><figcaption class=\"wp-element-caption\">Frau an einem Vervielfältgungsapparat. Übersetzung der Bildunterschrift: \"Vervielfältigungsapparate\". Ausschnitt aus S. 45 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19022,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_45-2-1.jpg\" alt=\"Zwei Frauen und ein älterer Mann beim Anfertigung von Lichtpausen. Übersetzung der Bildunterschrift: &quot;Lichtpauserei&quot;. Ausschnitt aus S. 45 des NEF-Fotoalbums.\" class=\"wp-image-19022\"/><figcaption class=\"wp-element-caption\">Zwei Frauen und ein älterer Mann beim Anfertigung von Lichtpausen. Übersetzung der Bildunterschrift: \"Lichtpauserei\". Ausschnitt aus S. 45 des NEF-Fotoalbums.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19023,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_45-3-1.jpg\" alt=\"Mitarbeiter im Zeichnungsarchiv an den Archivschränken. Übersetzung der Bildunterschrift: &quot;Zeichnungsarchiv&quot;. Ausschnitt aus S. 45 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19023\"/><figcaption class=\"wp-element-caption\">Mitarbeiter im Zeichnungsarchiv an den Archivschränken. Übersetzung der Bildunterschrift: \"Zeichnungsarchiv\". Ausschnitt aus S. 45 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19024,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_47-4.jpg\" alt=\"Gesamtansicht der S. 47 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19024\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 47 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19025,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_47-1-1.jpg\" alt=\"Zwei Frauen im Drahtlager. Übersetzung der Bildunterschrift: &quot;Drahtlager&quot;. Ausschnitt aus S. 47 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19025\"/><figcaption class=\"wp-element-caption\">Zwei Frauen im Drahtlager. Übersetzung der Bildunterschrift: \"Drahtlager\". Ausschnitt aus S. 47 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19026,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_47-2-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Muster von Halbfabrikaten, die auf Lager sind.&quot; Ausschnitt aus S. 47 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19026\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Muster von Halbfabrikaten, die auf Lager sind.\" Ausschnitt aus S. 47 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19027,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_47-3-1.jpg\" alt=\"Fünf Mitarbeiter beim Auseinanderbauen von Geräteteilen. Übersetzung der Bildunterschrift: &quot;Demontage von Geräten mit dem Ziel der Wiederverwendung von Einzelteilen&quot;. Ausschnitt aus S. 47 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19027\"/><figcaption class=\"wp-element-caption\">Fünf Mitarbeiter beim Auseinanderbauen von Geräteteilen. Übersetzung der Bildunterschrift: \"Demontage von Geräten mit dem Ziel der Wiederverwendung von Einzelteilen\". Ausschnitt aus S. 47 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19028,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_49-2.jpg\" alt=\"Gesamtansicht der S. 49 im Fotoalbum des NEF. Wer wann warum das andere Foto ausgeschnitten hat, ist leider nicht zu ermitteln.\" class=\"wp-image-19028\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 49 im Fotoalbum des NEF. Wer wann warum das andere Foto ausgeschnitten hat, ist leider nicht zu ermitteln.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19029,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_49-1-1.jpg\" alt=\"Übersetzung der Bildunterschrift: &quot;Arbeitskomitee&quot;. Ausschnitt aus S. 49 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19029\"/><figcaption class=\"wp-element-caption\">Übersetzung der Bildunterschrift: \"Arbeitskomitee\". Ausschnitt aus S. 49 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19030,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_51-3.jpg\" alt=\"Gesamtansicht der S. 51 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19030\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 51 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19031,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_51-1-1.jpg\" alt=\"Küche des NEF mit Küchenpersonal. Ausschnitt aus S. 51 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19031\"/><figcaption class=\"wp-element-caption\">Küche des NEF mit Küchenpersonal. Ausschnitt aus S. 51 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19032,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_51-2-1.jpg\" alt=\"Essensausgabe in der Kantine des NEF. Ausschnitt aus S. 51 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19032\"/><figcaption class=\"wp-element-caption\">Essensausgabe in der Kantine des NEF. Ausschnitt aus S. 51 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19033,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_53-4.jpg\" alt=\"Gesamtansicht der S. 53 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19033\"/><figcaption class=\"wp-element-caption\">Gesamtansicht der S. 53 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19034,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_53-1-1.jpg\" alt=\"Ausladen eines LKWS. Übersetzung der Bildunterschrift: &quot;Entladen einer Lieferung für die Ausstattung des Technischen Büros&quot;. Ausschnitt aus S. 53 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19034\"/><figcaption class=\"wp-element-caption\">Ausladen eines LKWS. Übersetzung der Bildunterschrift: \"Entladen einer Lieferung für die Ausstattung des Technischen Büros\". Ausschnitt aus S. 53 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19035,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_53-2-1.jpg\" alt=\"Aufladen eines Elektrokarrens und einesElektrokrans. Übersetzung der Bildunterschrift: &quot;Elektrokarren und Elektrokran beim Laden der Akkumulatoren&quot;. Ausschnitt aus S. 53 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19035\"/><figcaption class=\"wp-element-caption\">Aufladen eines Elektrokarrens und einesElektrokrans. Übersetzung der Bildunterschrift: \"Elektrokarren und Elektrokran beim Laden der Akkumulatoren\". Ausschnitt aus S. 53 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":19036,\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-full\"><img src=\"{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_53-3-1.jpg\" alt=\"Fahrzeug-Reparaturwerkstatt. Ausschnitt aus S. 53 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.\" class=\"wp-image-19036\"/><figcaption class=\"wp-element-caption\">Fahrzeug-Reparaturwerkstatt. Ausschnitt aus S. 53 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.</figcaption></figure>\n<!-- /wp:image -->"
      },
      "meta": {
        "_thumbnail_id": [
          "18974"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "0"
        ],
        "_iss_publication_price_cents": [
          "0"
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "5323457bb760c811462b698687e7e348c5b369f76446592973b9f87e22636d65",
        "post_content": "dbe9bba0460739b16d1e3c7a7c24ed2b94ae906a1c83a343cadb951a579331ab",
        "post_title": "483ec578050ba3258c5892e5cb8f0119061efc43d4d3f919d2ff0dd60ce8cc47",
        "post_excerpt": "81b0aa01331504279a61e04be10f7ae90ac1fb918a1c56710d718628e5e96671",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "19ffbbe49ebc8bfb9ad4607f66e17ba3a6d1cd015c5aaa6c8b57688ee406b0e4",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "92b71ea5aef73f9b18c9efca5a06a229230bad78f1fb68f04255c43b68f88f21"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [
          "d1937f50d97b8d60426394e4f524f4317a5ba9460f7d2bd825fc32f7fa1056e0"
        ],
        "_iss_editorial_enabled_publication": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "nef-album"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "bildmatrix",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "Fotoalbum",
            "title": "Album und Kontext",
            "body": "Nach Kriegsende wurde das das 6. WTB (wissenschaftlich-technische Buro) als „Nachrichtentechnik-Entwicklung und Fabrikation“, kurz NEF, von der sowjetischen Verwaltung in Ostberlin in den Raumen des ehemaligen zu AEG gehorenden „Fernmeldekabel- und Apparatefabrik Oberspree“ (FAO) im Bauteil B des Peter-Behrens-Baus in Oberschoneweide eingerichtet. Das NEF befasste sich wie das FAO zuvor mit der Entwicklung und Musterfertigung von Tragerfrequenzeinrichtungen, Fernsteuereinrichtungen, Fernschreibeinrichtungen und den dazugehorigen Messgeraten. Dieser Betrieb unterstand dem sowjetischen Ministerium fur Kommunikation. Zum 1. Januar 1950 wurde das NEF mit dem Vorgangerbetrieb des Werks fur Fernmeldewesen HF (spater Werk fur Fernsehelektronik WF), dem OSW (Oberspreewerk), fusioniert. Das Fotoalbum entstand vermutlich fur den sowjetischen Vorgesetzten im NEF."
          },
          {
            "type": "publication_rail",
            "kicker": "Album",
            "title": "Albumnavigation",
            "body": "",
            "rail_options": {
              "show_nav": false,
              "show_summary": false,
              "show_related": false,
              "variant": "detailed"
            }
          },
          {
            "type": "source",
            "kicker": "Quelle",
            "title": "Kontext, Quelle und Rechte",
            "body": "Kontext und Quelle: WF-Museum: NEF-Album, Quellen zur Geschichte des WF - Folge 4 - Fotoalben aus dem WF und Fundstucke zur Geschichte des NEF im Archiv des Industriesalons.Quelle und Rechte: WF-Museum / Industriesalon Schoneweide, CC BY-SA.",
            "links": [
              {
                "label": "Fundstucke zur Geschichte des NEF im Archiv des Industriesalons",
                "url": "{{SITE_URL}}/archivbeitraege/fundstuecke-zur-geschichte-des-nef-im-archiv-des-industriesalons/"
              }
            ]
          },
          {
            "type": "photoalbum",
            "kicker": "Album",
            "title": "NEF-Album",
            "body": "",
            "album_source": {
              "kind": "archive_set",
              "set_id": "19",
              "set_title": "NEF-Album"
            },
            "sheets": [
              {
                "source_kind": "archive_object",
                "source_id": "15125",
                "visible": true,
                "label": "S.1",
                "nav_title": "Einband",
                "caption": "Einband des Fotoalbums des NEF mit gravierter Metallplatte, auf der der Titel das Fotoalbums steht: \"MEP-SSSR /Technisches Büro für Leitungsverbindungen/ NEF / 1946 / Berlin\".  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_01-150x150.jpg",
                "position": 1,
                "source_set_id": "19",
                "member_id": "4468"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15127",
                "visible": true,
                "label": "S.2",
                "nav_title": "S.2",
                "caption": "Informationstext über das NEF auf kyrillisch. Die Übersetzung lautet: In dem auf dem Gelände des Werkes FAO AEG gelegenen Büro, das sich in Berlin Oberschöneweide, Ostendstraße 1-5 befindet, werden Apparaturen aller Art hergestellt.Vor dem Büro liegt die Aufgabe, neue Arten von Geräten für Leitungsverbindungen zu bearbeiten, Muster zu konstruieren und herzustellen, den technologischen Prozess vorzubereiten, spezielle Ausrüstungen für sowjetische Betriebe zu konstruieren und herzustellen und mit besonderen Hilfsmitteln Vorseriengeräte herzustellen .Die Hauptarbeit des Technischen Büros besteht in der Ausarbeitung neuer Arten von Geräten für Fernverbindungen – als universelle Systeme. Die Idee eines universellen Systems, das während des Krieges von deutschen Spezialisten vorgeschlagen wurde, besteht darin, Grundbausteine für die Kombination verschiedener Kommunikationswege zu schaffen, standardisierte Blöcke, die unterschiedliche Verbindungslinien wie etwa: Freileitungen, Kabellinien, Koaxialkabel und Verbindungen über Dezimeterwellen an das Fernmeldesystem anpassen. Das universelle System ersetzt die verschiedenen vorhandenen Arten von Fernmeldegeräten.Die Vereinheitlichung der Geräte vereinfacht außerordentlich ihre Produktion und Nutzung. Damit bringen universelle Systeme zweifellos ein Interesse zur Angleichung der Schaltungen und Konstruktionen mit sich, insbesondere der Konstruktion besonderer Halbfabrikate.Die Arbeiten im Bereich der universellen Systeme werden auf die Entwicklung von Grundlagen ausgerichtet, sie führen zur Ausarbeitung prinzipieller Schaltungen, Konstruktionen, technologischer Prozesse und Werkzeugbau.Das Technische Büro leistet wesentliche Hilfe für sowjetische Werke bei der Entwicklung neuer Systeme der automatischen Telefonvermittlung des Typs „F“ und für Telefonapparate der Firma Siemens und Halske. Auf diesem Arbeitsgebiet beschränkt sich die Arbeit auf das Sammeln der erforderlichen technischen Dokumentat",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_03-150x150.jpg",
                "position": 2,
                "source_set_id": "19",
                "member_id": "4469"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15129",
                "visible": true,
                "label": "S.3",
                "nav_title": "Organisationsdiagramm des NEF, Stand: 1. Juni 1946",
                "caption": "Organisationsdiagramm des NEF, Stand: 1. Juni 1946.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_05-150x150.jpg",
                "position": 3,
                "source_set_id": "19",
                "member_id": "4470"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15131",
                "visible": true,
                "label": "S.4",
                "nav_title": "S.4",
                "caption": "Gesamtansicht der S.7 im Fotoalbum des NEF.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.t.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_07-150x150.jpg",
                "position": 4,
                "source_set_id": "19",
                "member_id": "4471"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15133",
                "visible": true,
                "label": "S.5",
                "nav_title": "Ansicht der Peter-Behrens-Baus mit Turm im Frühjahr 1946.",
                "caption": "Ansicht der Peter-Behrens-Baus mit Turm im Frühjahr 1946. Übersetzung der Bildunterschrift: \"Gebäude, in dem sich das Technische Büro befindet.\"  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_07-1-150x150.jpg",
                "position": 5,
                "source_set_id": "19",
                "member_id": "4472"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15135",
                "visible": true,
                "label": "S.6",
                "nav_title": "S.6",
                "caption": "Gesamtansicht der S.11 im Fotoalbum des NEF.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.igt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_11-150x150.jpg",
                "position": 6,
                "source_set_id": "19",
                "member_id": "4473"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15137",
                "visible": true,
                "label": "S.7",
                "nav_title": "Leitende Angestellte des NEF.",
                "caption": "Leitende Angestellte des NEF. Übersetzung der Bildunterschrift: \"Leitende Spezialisten bei einer Abteilungsbesprechung / Stehend von links nach rechts: 1. Dipl.Ing. Kleinschnitz / Hochfrequenzsysteme 2. Dr. Burghardt / (?) 3. Dipl.Ing. Schneider / elektrische Filter 4. Dipl.Ing. Pässler / Telefonie über HF Leitungen / 5. Dipl.Ing. Mählis / Telegrafen Endgeräte 6. Dipl.Ing. Flurl / Material und Halbzeuge 7. Dipl.Ing. Gerlach / Elektroakustik / 8. Dipl.Ing. Seidel / Messtechnik 9. Dipl.Ing. Grieger / Verstärker 10.Dipl.Ing. Oehlen / Netzprojektierung Fernverbindung.Sitzend von links nach rechts: 1. Dr. Weinitschke / Projektierung Fernlinien 2. Dr. Gessler / Werkstatt Universalsysteme 3. Dr. Kluge / Werkstatt Sonderfertigung 4. Dr. Thierbach / Weitverkehrssysteme 5. Ing. Roloff/ Techn.-Ökonom. Projektierung Weitverkehrsnetze 6. Dipl.Ing. Domsch / Technische Information.\" Ausschnitt aus S. 11 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_11-1-150x150.jpg",
                "position": 7,
                "source_set_id": "19",
                "member_id": "4474"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15139",
                "visible": true,
                "label": "S.8",
                "nav_title": "S.8",
                "caption": "Gesamtansicht der S.13 im Fotoalbum des NEF, Fotos Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_13-150x150.jpg",
                "position": 8,
                "source_set_id": "19",
                "member_id": "4475"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15141",
                "visible": true,
                "label": "S.9",
                "nav_title": "Geräte für Fernverbindungen ME, die vom Werk FAO-AEG hergestellt wurden, werden für Laborexperimente…",
                "caption": "Übersetzung der Bildunterschrift: \"Geräte für Fernverbindungen ME, die vom Werk FAO-AEG hergestellt wurden, werden für Laborexperimente genutzt.\" Ausschnitt aus S. 13 des NEF-Fotoalbums.\n Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_13-1-150x150.jpg",
                "position": 9,
                "source_set_id": "19",
                "member_id": "4476"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15143",
                "visible": true,
                "label": "S.10",
                "nav_title": "Telefonapparatur für Linien hoher Leistung, hergestellt vom Werk FAO-AEG wird für Laborexperimente g…",
                "caption": "Übersetzung der Bildunterschrift: \"Telefonapparatur für Linien hoher Leistung, hergestellt vom Werk FAO-AEG wird für Laborexperimente genutzt.\" Ausschnitt aus S. 13 des Fotoalbum NEF.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_13-2-1-150x150.jpg",
                "position": 10,
                "source_set_id": "19",
                "member_id": "4477"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15145",
                "visible": true,
                "label": "S.11",
                "nav_title": "S.11",
                "caption": "Gesamtansicht der S.15 im Fotoalbum des NEF, Fotos Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_15-150x150.jpg",
                "position": 11,
                "source_set_id": "19",
                "member_id": "4478"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15147",
                "visible": true,
                "label": "S.12",
                "nav_title": "Platz für die Aufzeichnung der Frequenzcharakteristik.",
                "caption": "Übersetzung der Bildunterschrift: \"Platz für die Aufzeichnung der Frequenzcharakteristik.\" Ausschnitt aus S. 15 des NEF-Fotoalbums.\nFoto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_15-1-150x150.jpg",
                "position": 12,
                "source_set_id": "19",
                "member_id": "4479"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15149",
                "visible": true,
                "label": "S.13",
                "nav_title": "Arbeitsplatz für die Prüfung von Halbfabrikaten.",
                "caption": "Übersetzung der Bildunterschrift: \"Arbeitsplatz für die Prüfung von Halbfabrikaten.\" Ausschnitt aus S. 15 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_15-2-150x150.jpg",
                "position": 13,
                "source_set_id": "19",
                "member_id": "4480"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15151",
                "visible": true,
                "label": "S.14",
                "nav_title": "S.14",
                "caption": "Gesamtansicht der S.17 im Fotoalbum des NEF.  Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_17-150x150.jpg",
                "position": 14,
                "source_set_id": "19",
                "member_id": "4481"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15153",
                "visible": true,
                "label": "S.15",
                "nav_title": "Konstruktionsbüro der Abteilung bei der Arbeit.",
                "caption": "Übersetzung der Bildunterschrift: \"Konstruktionsbüro der Abteilung bei der Arbeit.\" Ausschnitt aus S. 17 des NEF-Fotoalbum. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_17-1-150x150.jpg",
                "position": 15,
                "source_set_id": "19",
                "member_id": "4482"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15155",
                "visible": true,
                "label": "S.16",
                "nav_title": "Arbeitsplatz für die Prüfung von Filtern",
                "caption": "Übersetzung der Bildunterschrift: \"Arbeitsplatz für die Prüfung von Filtern\". Ausschnitt aus S. 17 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_17-2-150x150.jpg",
                "position": 16,
                "source_set_id": "19",
                "member_id": "4483"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15157",
                "visible": true,
                "label": "S.17",
                "nav_title": "S.17",
                "caption": "Gesamtansicht der S.19 im Fotoalbum des NEF. Bildunterschrift: \"Maschinenraum, der für die Versorgung der Laboratorien genutzt wird.\"  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_19-150x150.jpg",
                "position": 17,
                "source_set_id": "19",
                "member_id": "4484"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15159",
                "visible": true,
                "label": "S.18",
                "nav_title": "Blick auf die Fensterseite des Maschinenraums",
                "caption": "Blick auf die Fensterseite des Maschinenraums. Ausschnitt aus S. 19 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_19-1-150x150.jpg",
                "position": 18,
                "source_set_id": "19",
                "member_id": "4485"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15161",
                "visible": true,
                "label": "S.19",
                "nav_title": "Blick auf die dem Fenster gegenüberliegende Seite des Maschinenraums",
                "caption": "Blick auf die dem Fenster gegenüberliegende Seite des Maschinenraums. Ausschnitt aus S. 19 des NEF-Fotoalbums.\nFoto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_19-2-150x150.jpg",
                "position": 19,
                "source_set_id": "19",
                "member_id": "4486"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15163",
                "visible": true,
                "label": "S.20",
                "nav_title": "S.20",
                "caption": "Gesamtansicht der S. 21 im Fotoalbum des NEF.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_21-150x150.jpg",
                "position": 20,
                "source_set_id": "19",
                "member_id": "4487"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15165",
                "visible": true,
                "label": "S.21",
                "nav_title": "Ansicht von Baugruppen.",
                "caption": "Ansicht von Baugruppen. Übersetzung der Bildunterschrift: \"Baugruppen, die in universellen Systemen arbeiten.\" Ausschnitt aus S. 21 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_21-1-150x150.jpg",
                "position": 21,
                "source_set_id": "19",
                "member_id": "4488"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15167",
                "visible": true,
                "label": "S.22",
                "nav_title": "S.22",
                "caption": "Gesamtansicht der S. 25 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_25-150x150.jpg",
                "position": 22,
                "source_set_id": "19",
                "member_id": "4489"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15169",
                "visible": true,
                "label": "S.23",
                "nav_title": "Werkstattbereich der Versuchsabteilung.",
                "caption": "Übersetzung der Bildunterschrift: \"Werkstattbereich der Versuchsabteilung.\" Ausschnitt aus S. 25 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_25-1-150x150.jpg",
                "position": 23,
                "source_set_id": "19",
                "member_id": "4490"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15171",
                "visible": true,
                "label": "S.24",
                "nav_title": "Bereich der Versuchsabteilung beim Zusammenbau von Geräten.",
                "caption": "Übersetzung der Bildunterschrift: \"Bereich der Versuchsabteilung beim Zusammenbau von Geräten.\" Ausschnitt aus S. 25 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_25-2-150x150.jpg",
                "position": 24,
                "source_set_id": "19",
                "member_id": "4491"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15173",
                "visible": true,
                "label": "S.25",
                "nav_title": "S.25",
                "caption": "Gesamtansicht der S. 27 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_27-150x150.jpg",
                "position": 25,
                "source_set_id": "19",
                "member_id": "4492"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15175",
                "visible": true,
                "label": "S.26",
                "nav_title": "Wickelei der Versuchswerkstatt.",
                "caption": "Wickelei der Versuchswerkstatt. Übersetzung der Bildunterschrift: \"Wickeln von Netztransformatoren.\" Ausschnitt aus S. 27 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_27-1-150x150.jpg",
                "position": 26,
                "source_set_id": "19",
                "member_id": "4493"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15177",
                "visible": true,
                "label": "S.27",
                "nav_title": "Wickelei der Versuchswerkstatt.",
                "caption": "Wickelei der Versuchswerkstatt. Übersetzung der Bildunterschrift: \"Wicklerei der Versuchswerkstatt, rechts: Bänke für das Wickeln von Ringkernen.\" Ausschnitt aus S. 27 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_27-2-150x150.jpg",
                "position": 27,
                "source_set_id": "19",
                "member_id": "4494"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15179",
                "visible": true,
                "label": "S.28",
                "nav_title": "S.28",
                "caption": "Gesamtansicht der S. 29 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_29-150x150.jpg",
                "position": 28,
                "source_set_id": "19",
                "member_id": "4495"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15181",
                "visible": true,
                "label": "S.29",
                "nav_title": "Konstrukteure beim Anfertigen technischer Zeichnungen.",
                "caption": "Konstrukteure beim Anfertigen technischer Zeichnungen. Übersetzung der Bildunterschrift: \"Konstruktionsbüro für Sondergeräte.\" Ausschnitt aus S. 29 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_29-1-150x150.jpg",
                "position": 29,
                "source_set_id": "19",
                "member_id": "4496"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15183",
                "visible": true,
                "label": "S.30",
                "nav_title": "Büro für technische Information.",
                "caption": "Büro für technische Information. Übersetzung der Bildunterschrift: \"Technologisches Büro.\" Ausschnitt aus S. 29 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_29-2-150x150.jpg",
                "position": 30,
                "source_set_id": "19",
                "member_id": "4497"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15185",
                "visible": true,
                "label": "S.31",
                "nav_title": "S.31",
                "caption": "Gesamtansicht der S. 31 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_31-150x150.jpg",
                "position": 31,
                "source_set_id": "19",
                "member_id": "4498"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15187",
                "visible": true,
                "label": "S.32",
                "nav_title": "Schlosserwerkstatt der Geräteabteilung /im Vordergrund/.",
                "caption": "Übersetzung der Bildunterschrift: \"Schlosserwerkstatt der Geräteabteilung /im Vordergrund/.\" Ausschnitt aus S. 31 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_31-1-150x150.jpg",
                "position": 32,
                "source_set_id": "19",
                "member_id": "4499"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15189",
                "visible": true,
                "label": "S.33",
                "nav_title": "S.33",
                "caption": "Gesamtansicht der S. 33 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_33-150x150.jpg",
                "position": 33,
                "source_set_id": "19",
                "member_id": "4500"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15191",
                "visible": true,
                "label": "S.34",
                "nav_title": "Schleifmaschinen im Einsatz.",
                "caption": "Schleifmaschinen im Einsatz. Übersetzung der Bildunterschrift: \"Gruppe von Schleifarbeitsplätzen der Geräteabteilung\". Ausschnitt aus S. 33 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_33-1-150x150.jpg",
                "position": 34,
                "source_set_id": "19",
                "member_id": "4501"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15193",
                "visible": true,
                "label": "S.35",
                "nav_title": "Fräsmaschinen im Einsatz.",
                "caption": "Fräsmaschinen im Einsatz. Übersetzung der Bildunterschrift: \"Gruppe von Fräsarbeitsplätzen der Geräteabteilung\". Ausschnitt aus S. 33 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_33-2-150x150.jpg",
                "position": 35,
                "source_set_id": "19",
                "member_id": "4502"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15195",
                "visible": true,
                "label": "S.36",
                "nav_title": "S.36",
                "caption": "Gesamtansicht der S. 35 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_35-150x150.jpg",
                "position": 36,
                "source_set_id": "19",
                "member_id": "4503"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15197",
                "visible": true,
                "label": "S.37",
                "nav_title": "Drehbänke und Hobelmaschinen im Einsatz.",
                "caption": "Drehbänke und Hobelmaschinen im Einsatz. Übersetzung der Bildunterschrift: \"Gruppe von Drehbänken und Hobelmaschinen im Werkzeugbau.\" Ausschnitt aus S. 35 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_35-1-150x150.jpg",
                "position": 37,
                "source_set_id": "19",
                "member_id": "4504"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15199",
                "visible": true,
                "label": "S.38",
                "nav_title": "Drehbänke im Einsatz.",
                "caption": "Drehbänke im Einsatz. Übersetzung der Bildunterschrift: \"Gruppe von Drehbänken der Geräteabteilung.\" Ausschnitt aus S. 35 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_35-2-150x150.jpg",
                "position": 38,
                "source_set_id": "19",
                "member_id": "4505"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15201",
                "visible": true,
                "label": "S.39",
                "nav_title": "S.39",
                "caption": "Gesamtansicht der S. 37 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_37-150x150.jpg",
                "position": 39,
                "source_set_id": "19",
                "member_id": "4506"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15203",
                "visible": true,
                "label": "S.40",
                "nav_title": "Präzisions-Universalbohrer.",
                "caption": "Präzisions-Universalbohrer. Übersetzung der Bildunterschrift: \"Präzisions-Universalbohrer Arbeitsplatz\". Ausschnitt aus S. 37 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_37-1-150x150.jpg",
                "position": 40,
                "source_set_id": "19",
                "member_id": "4507"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15205",
                "visible": true,
                "label": "S.41",
                "nav_title": "Hobelmaschine.",
                "caption": "Hobelmaschine. Übersetzung der Bildunterschrift: \"Hobelmaschinen Arbeitsplatz\". Ausschnitt aus S. 37 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_37-2-150x150.jpg",
                "position": 41,
                "source_set_id": "19",
                "member_id": "4508"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15207",
                "visible": true,
                "label": "S.42",
                "nav_title": "S.42",
                "caption": "Gesamtansicht der S. 39 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_39-150x150.jpg",
                "position": 42,
                "source_set_id": "19",
                "member_id": "4509"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15209",
                "visible": true,
                "label": "S.43",
                "nav_title": "Universal-Fräsmaschine im Einsatz.",
                "caption": "Universal-Fräsmaschine im Einsatz. Übersetzung der Bildunterschrift: \"Universal-Fräsmaschinen Arbeitsplatz der Geräteabteilung\". Ausschnitt aus S. 39 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_39-1-150x150.jpg",
                "position": 43,
                "source_set_id": "19",
                "member_id": "4510"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15211",
                "visible": true,
                "label": "S.44",
                "nav_title": "Vertikal-Fräsmaschine im Einsatz.",
                "caption": "Vertikal-Fräsmaschine im Einsatz. Übersetzung der Bildunterschrift: \"Vertikal-Fräsmaschinen Arbeitsplatz der Geräteabteilung\". Ausschnitt aus S. 39 des NEF-Fotoalbums. Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_39-2-150x150.jpg",
                "position": 44,
                "source_set_id": "19",
                "member_id": "4511"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15213",
                "visible": true,
                "label": "S.45",
                "nav_title": "S.45",
                "caption": "Gesamtansicht der S. 41 im Fotoalbum des NEF.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_41-150x150.jpg",
                "position": 45,
                "source_set_id": "19",
                "member_id": "4512"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15215",
                "visible": true,
                "label": "S.46",
                "nav_title": "Garnitur von Werkzeugen und Vorrichtungen für das Flachrelais 70, die in der Werkstatt gefertigt wur…",
                "caption": "Übersetzung der Bildunterschrift: \"Garnitur von Werkzeugen und Vorrichtungen für das Flachrelais 70, die in der Werkstatt gefertigt wurden\". Ausschnitt aus S. 41 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_41-1-150x150.jpg",
                "position": 46,
                "source_set_id": "19",
                "member_id": "4513"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15217",
                "visible": true,
                "label": "S.47",
                "nav_title": "S.47",
                "caption": "Gesamtansicht der S. 45 im Fotoalbum des NEF, Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_45-150x150.jpg",
                "position": 47,
                "source_set_id": "19",
                "member_id": "4514"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15219",
                "visible": true,
                "label": "S.48",
                "nav_title": "Frau an einem Vervielfältgungsapparat.",
                "caption": "Frau an einem Vervielfältgungsapparat. Übersetzung der Bildunterschrift: \"Vervielfältigungsapparate\". Ausschnitt aus S. 45 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_45-1-150x150.jpg",
                "position": 48,
                "source_set_id": "19",
                "member_id": "4515"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15221",
                "visible": true,
                "label": "S.49",
                "nav_title": "Zwei Frauen und ein älterer Mann beim Anfertigung von Lichtpausen.",
                "caption": "Zwei Frauen und ein älterer Mann beim Anfertigung von Lichtpausen. Übersetzung der Bildunterschrift: \"Lichtpauserei\". Ausschnitt aus S. 45 des NEF-Fotoalbums.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_45-2-150x150.jpg",
                "position": 49,
                "source_set_id": "19",
                "member_id": "4516"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15223",
                "visible": true,
                "label": "S.50",
                "nav_title": "Mitarbeiter im Zeichnungsarchiv an den Archivschränken.",
                "caption": "Mitarbeiter im Zeichnungsarchiv an den Archivschränken. Übersetzung der Bildunterschrift: \"Zeichnungsarchiv\". Ausschnitt aus S. 45 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_45-3-150x150.jpg",
                "position": 50,
                "source_set_id": "19",
                "member_id": "4517"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15225",
                "visible": true,
                "label": "S.51",
                "nav_title": "S.51",
                "caption": "Gesamtansicht der S. 47 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_47-150x150.jpg",
                "position": 51,
                "source_set_id": "19",
                "member_id": "4518"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15227",
                "visible": true,
                "label": "S.52",
                "nav_title": "Zwei Frauen im Drahtlager.",
                "caption": "Zwei Frauen im Drahtlager. Übersetzung der Bildunterschrift: \"Drahtlager\". Ausschnitt aus S. 47 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_47-1-150x150.jpg",
                "position": 52,
                "source_set_id": "19",
                "member_id": "4519"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15229",
                "visible": true,
                "label": "S.53",
                "nav_title": "Muster von Halbfabrikaten, die auf Lager sind.",
                "caption": "Übersetzung der Bildunterschrift: \"Muster von Halbfabrikaten, die auf Lager sind.\" Ausschnitt aus S. 47 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_47-2-150x150.jpg",
                "position": 53,
                "source_set_id": "19",
                "member_id": "4520"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15231",
                "visible": true,
                "label": "S.54",
                "nav_title": "Fünf Mitarbeiter beim Auseinanderbauen von Geräteteilen.",
                "caption": "Fünf Mitarbeiter beim Auseinanderbauen von Geräteteilen. Übersetzung der Bildunterschrift: \"Demontage von Geräten mit dem Ziel der Wiederverwendung von Einzelteilen\". Ausschnitt aus S. 47 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_47-3-150x150.jpg",
                "position": 54,
                "source_set_id": "19",
                "member_id": "4521"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15233",
                "visible": true,
                "label": "S.55",
                "nav_title": "S.55",
                "caption": "Gesamtansicht der S. 49 im Fotoalbum des NEF. Wer wann warum das andere Foto ausgeschnitten hat, ist leider nicht zu ermitteln.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_49-150x150.jpg",
                "position": 55,
                "source_set_id": "19",
                "member_id": "4522"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15235",
                "visible": true,
                "label": "S.56",
                "nav_title": "Arbeitskomitee",
                "caption": "Übersetzung der Bildunterschrift: \"Arbeitskomitee\". Ausschnitt aus S. 49 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_49-1-150x150.jpg",
                "position": 56,
                "source_set_id": "19",
                "member_id": "4523"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15237",
                "visible": true,
                "label": "S.57",
                "nav_title": "S.57",
                "caption": "Gesamtansicht der S. 51 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_51-150x150.jpg",
                "position": 57,
                "source_set_id": "19",
                "member_id": "4524"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15239",
                "visible": true,
                "label": "S.58",
                "nav_title": "Küche des NEF mit Küchenpersonal",
                "caption": "Küche des NEF mit Küchenpersonal. Ausschnitt aus S. 51 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_51-1-150x150.jpg",
                "position": 58,
                "source_set_id": "19",
                "member_id": "4525"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15241",
                "visible": true,
                "label": "S.59",
                "nav_title": "Essensausgabe in der Kantine des NEF",
                "caption": "Essensausgabe in der Kantine des NEF. Ausschnitt aus S. 51 des NEF-Fotoalbums.\nFoto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_51-2-150x150.jpg",
                "position": 59,
                "source_set_id": "19",
                "member_id": "4526"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15243",
                "visible": true,
                "label": "S.60",
                "nav_title": "S.60",
                "caption": "Gesamtansicht der S. 53 im Fotoalbum des NEF. Fotos, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_53-150x150.jpg",
                "position": 60,
                "source_set_id": "19",
                "member_id": "4527"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15245",
                "visible": true,
                "label": "S.61",
                "nav_title": "Ausladen eines LKWS.",
                "caption": "Ausladen eines LKWS. Übersetzung der Bildunterschrift: \"Entladen einer Lieferung für die Ausstattung des Technischen Büros\". Ausschnitt aus S. 53 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_53-1-150x150.jpg",
                "position": 61,
                "source_set_id": "19",
                "member_id": "4528"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15247",
                "visible": true,
                "label": "S.62",
                "nav_title": "Aufladen eines Elektrokarrens und einesElektrokrans.",
                "caption": "Aufladen eines Elektrokarrens und einesElektrokrans. Übersetzung der Bildunterschrift: \"Elektrokarren und Elektrokran beim Laden der Akkumulatoren\". Ausschnitt aus S. 53 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_53-2-150x150.jpg",
                "position": 62,
                "source_set_id": "19",
                "member_id": "4529"
              },
              {
                "source_kind": "archive_object",
                "source_id": "15249",
                "visible": true,
                "label": "S.63",
                "nav_title": "Fahrzeug-Reparaturwerkstatt",
                "caption": "Fahrzeug-Reparaturwerkstatt. Ausschnitt aus S. 53 des NEF-Fotoalbums.  Foto, Juni 1946. Das Digitalisat wurde vom Originalalbum angefertigt.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Album_NEF_Seite_53-3-150x150.jpg",
                "position": 63,
                "source_set_id": "19",
                "member_id": "4530"
              }
            ]
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 19038,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "19039"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          0
        ],
        "_iss_publication_price_cents": [
          0
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "f521f87326c8ecb7bf5549d8ce49c2a20b01294148760b3f0d7308880c5662b1",
        "post_content": "a6b3441e91c8fd50a19fdb03045d0f8254abc33c2e5e049f822240fccf0c0bcc",
        "post_title": "7b75f4212e3cdda0ea5469614f917698eb98b54f2dd2a2ac770a058954e5d403",
        "post_excerpt": "efcf0e7ac9a650a4edc3626ea34dd255100bbf27d1c7f45243de65f5a2a58da7",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "4a5c4c014130d846f65392637f35e66062a64d6ab269d7e679e6d090c4d52aee",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "e3ae51cd611c3ae09dd8d9c7319a7d3b018869b5a71d465cd1e00818400f5273"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [
          "72b598c6832e68d1f583589f2303f18046419381086cc00de73231f47a0c1422"
        ],
        "_iss_editorial_enabled_publication": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "fotoalbum-produktion-im-werk-fuer-fernmeldewesen-hf-1951"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "bildmatrix",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "Fotoalbum",
            "title": "Album und Kontext",
            "body": "Im August 1945 von den Sowjets als LKVO gegrundet, diente das Werk fur Fernmeldewesen vor allem der Entwicklung von elektronischen Rohren und Geraten als Reparationsleistungen fur die Sowjetunion. Von 1950-1952 wurde im HF Bildrohren fur das sowjetische Fernsehgerat „Leningrad T2“ hergestellt. Es war von Juli 1946 bis April 1952 eine SAG. Das Album entstand vermutlich fur den sowjetischen Vorgesetzten im HF."
          },
          {
            "type": "publication_rail",
            "kicker": "Album",
            "title": "Albumnavigation",
            "body": "",
            "rail_options": {
              "show_nav": false,
              "show_summary": false,
              "show_related": false,
              "variant": "detailed"
            }
          },
          {
            "type": "source",
            "kicker": "Quelle",
            "title": "Kontext, Quelle und Rechte",
            "body": "Kontext und Quelle: WF-Museum: Fotoalbum Produktion im Werk fur Fernmeldewesen (HF), 1951, Quellen zur Geschichte des WF - Folge 4 - Fotoalben aus dem WF und Quellen zur Geschichte des WF - Folge 3 - Forschungs- und Entwicklungsberichte aus dem OSW/HF/WF.Quelle und Rechte: WF-Museum / Industriesalon Schoneweide, CC BY-SA.",
            "links": [
              {
                "label": "WF-Museum: Fotoalbum Produktion im Werk fur Fernmeldewesen (HF), 1951",
                "url": "https://wf-museum.de/home-2/betriebsfotoalben/fotoalbum-produktion-im-werk-fuer-fernmeldewesen-hf-1951-3/"
              },
              {
                "label": "Quellen zur Geschichte des WF - Folge 4 - Fotoalben aus dem WF",
                "url": "{{SITE_URL}}/archivbeitraege/quellen-zur-geschichte-des-wf-folge-4-fotoalben-aus-dem-wf/"
              },
              {
                "label": "Quellen zur Geschichte des WF - Folge 3 - Forschungs- und Entwicklungsberichte aus dem OSW/HF/WF",
                "url": "{{SITE_URL}}/archivbeitraege/quellen-zur-geschichte-des-wf-folge-3-forschungs-und-entwicklungsberichte-aus-dem-osw-hf-wf/"
              }
            ]
          },
          {
            "type": "photoalbum",
            "kicker": "Album",
            "title": "Fotoalbum Produktion im Werk fur Fernmeldewesen (HF), 1951",
            "body": "",
            "album_source": {
              "kind": "manual",
              "set_id": "",
              "set_title": "WF-Museum"
            },
            "sheets": [
              {
                "source_kind": "wp_media",
                "source_id": "19039",
                "visible": true,
                "label": "Blatt 01",
                "nav_title": "Blatt 01",
                "caption": "Die 2. Seite des Fotoalbums mit der Beschriftung unterhalb des Fotos \"Presstellerautomat (Obj. I/1)\". Das Album beinhaltet 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-2-1-150x150.jpg",
                "position": 1,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19040",
                "visible": true,
                "label": "Blatt 02",
                "nav_title": "Blatt 02",
                "caption": "Die 4. Seite des Fotoalbums mit der Beschriftung unterhalb des Fotos \"Stengelansetzmaschine (Obj. II/ 2a) mit durchlauftemperaturofen (Obj. VII/ 4c)\". Das Album beinhaltet 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-4-1-150x150.jpg",
                "position": 2,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19041",
                "visible": true,
                "label": "Blatt 03",
                "nav_title": "Blatt 03",
                "caption": "Die 5. Seite des Fotoalbums mit der Beschriftung unterhalb des Fotos \"Gitterkerbautomaten, Raumaufnahme (Obj. II/18)\". Das Album beinhaltet 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-5-1-150x150.jpg",
                "position": 3,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19042",
                "visible": true,
                "label": "Blatt 04",
                "nav_title": "Blatt 04",
                "caption": "Die 6. Seite des Fotoalbums mit der Beschriftung unterhalb des Fotos \"Gitterkerbautomat (Obj. II/18)\". Das Album beinhaltet 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-6-1-150x150.jpg",
                "position": 4,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19043",
                "visible": true,
                "label": "Blatt 05",
                "nav_title": "Blatt 05",
                "caption": "Die 7. Seite des Fotoalbums mit der Beschriftung unterhalb des Fotos \"Handgitterwickelmaschinen (Obj. II/28)\". Das Album beinhaltet 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-7-1-150x150.jpg",
                "position": 5,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19044",
                "visible": true,
                "label": "Blatt 06",
                "nav_title": "Blatt 06",
                "caption": "Die 8. Seite des Fotoalbums mit der Beschriftung unterhalb des Fotos \"Drahtricht- und Schneidemaschine (Obj. XIIIc/17a)\". Das Album beinhaltet 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-8-1-150x150.jpg",
                "position": 6,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19045",
                "visible": true,
                "label": "Blatt 07",
                "nav_title": "Blatt 07",
                "caption": "Die 9. Seite des Fotoalbums mit der Beschriftung unterhalb des Fotos \"Wasserstoffglühofen (Obj. II/19)\". Das Album beinhaltet 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-9-1-150x150.jpg",
                "position": 7,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19046",
                "visible": true,
                "label": "Blatt 08",
                "nav_title": "Blatt 08",
                "caption": "Die 10. Seite des Fotoalbums mit der Beschriftung unterhalb des Fotos \"Abt. Aufbau der Rundfunkröhrenfertigung (Obj. II), Raumaufnahme\". Das Album beinhaltet 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-10-1-150x150.jpg",
                "position": 8,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19047",
                "visible": true,
                "label": "Seite 11",
                "nav_title": "Seite 11",
                "caption": "Punktschweissmaschine (Obj. II/8), Teilraumaufnahme, Seite 11 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-11-1-150x150.jpg",
                "position": 9,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19048",
                "visible": true,
                "label": "Seite 12",
                "nav_title": "Seite 12",
                "caption": "\"Punktschweissmaschine (Obj. II/8), kpl. mit Tisch\", Seite 12 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-12-1-150x150.jpg",
                "position": 10,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19049",
                "visible": true,
                "label": "Seite 13",
                "nav_title": "Seite 13",
                "caption": "Pumpautomaten (Obj. III) Teilraumaufnahme, Seite 13 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-13-1-150x150.jpg",
                "position": 11,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19050",
                "visible": true,
                "label": "Seite 14",
                "nav_title": "Seite 14",
                "caption": "48-tlg. Pumpautomat mit Schaltpult (Obj. III/1), Seite 14 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-14-1-150x150.jpg",
                "position": 12,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19051",
                "visible": true,
                "label": "Seite 15",
                "nav_title": "Seite 15",
                "caption": "20 KW-Sender (Obj. III/3), Seite 15 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-15-1-150x150.jpg",
                "position": 13,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19052",
                "visible": true,
                "label": "Seite 16",
                "nav_title": "Seite 16",
                "caption": "18-tlg. Einschmelzmaschine (Obj. I/7), Seite 16 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-16-1-150x150.jpg",
                "position": 14,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19053",
                "visible": true,
                "label": "Seite 17",
                "nav_title": "Seite 17",
                "caption": "30-tlg. Sockelmaschine (Obj. I/10), Seite 17 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-17-1-150x150.jpg",
                "position": 15,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19054",
                "visible": true,
                "label": "Seite 18",
                "nav_title": "Seite 18",
                "caption": "Formierrahmen (Obj. IV/2), Seite 18 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-18-1-150x150.jpg",
                "position": 16,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19055",
                "visible": true,
                "label": "Seite 19",
                "nav_title": "Seite 19",
                "caption": "Formierrahmen für Röhre P 50 (Obj. IV/3), Seite 19 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-19-2-150x150.jpg",
                "position": 17,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19056",
                "visible": true,
                "label": "Seite 20",
                "nav_title": "Seite 20",
                "caption": "Karussellmesstisch (Obj. IV/1), Seite 20 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-20-1-150x150.jpg",
                "position": 18,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19057",
                "visible": true,
                "label": "Seite 24",
                "nav_title": "Seite 24",
                "caption": "Fertigungswerkstatt für das Anhalsen der Kolben, (Obj. VII), Raum-Teilaufnahme, Seite 24 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-24-1-150x150.jpg",
                "position": 19,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19058",
                "visible": true,
                "label": "Seite 25",
                "nav_title": "Seite 25",
                "caption": "\"Horizontale Einschmelzmaschine (Obj. VII/I)\", Seite 25 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-25-1-150x150.jpg",
                "position": 20,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19059",
                "visible": true,
                "label": "Seite 26",
                "nav_title": "Seite 26",
                "caption": "12-tlg. Maschine zum Einschmelzen der Durchführungen (Obj. VII/2) Bildröhrenfertigung, Seite 26 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-26-1-150x150.jpg",
                "position": 21,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19060",
                "visible": true,
                "label": "Seite 27",
                "nav_title": "Seite 27",
                "caption": "Temperofen 0,7 m³ zum Tempern der Bildröhrenkolben (Obj. VII/4), Seite 27 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-27-1-150x150.jpg",
                "position": 22,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19061",
                "visible": true,
                "label": "Seite 28",
                "nav_title": "Seite 28",
                "caption": "Vinidur-Bottiche, (Obj. VI/13a) und Entlüftungsanlage zum Waschraum für Bildröhrenfertigung (Obj. VII/4d), Seite 28 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-28-1-150x150.jpg",
                "position": 23,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19062",
                "visible": true,
                "label": "Seite 29",
                "nav_title": "Seite 29",
                "caption": "Settelraum für Bildröhren (Obj. I/9) Raumaufnahme, Seite 29 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-29-1-150x150.jpg",
                "position": 24,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19063",
                "visible": true,
                "label": "Seite 30",
                "nav_title": "Seite 30",
                "caption": "Tauchanlage zur Bekohlung der Bildröhren (Obj. VI/5), Seite 30 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-30-1-150x150.jpg",
                "position": 25,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19064",
                "visible": true,
                "label": "Seite 31",
                "nav_title": "Seite 31",
                "caption": "\"Wanderofen im Bekohlungsraum (Obj. VII/2) Teilaufnahme\", Seite 31 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-31-1-150x150.jpg",
                "position": 26,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19065",
                "visible": true,
                "label": "Seite 32",
                "nav_title": "Seite 32",
                "caption": "Wanderofen im Bekohlungsraum (Obj. VI/2) Teilaufnahme, Seite 32 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-32-1-150x150.jpg",
                "position": 27,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19066",
                "visible": true,
                "label": "Seite 33",
                "nav_title": "Seite 33",
                "caption": "8-tlg. Einschmelzmaschine für Bildröhren (Obj. VII/3), Seite 33 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-33-1-150x150.jpg",
                "position": 28,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19067",
                "visible": true,
                "label": "Seite 34",
                "nav_title": "Seite 34",
                "caption": "Seite 34. des Albums mit der Beschriftung \"Pumpstände für Bildröhren (Obj. VI) Raumaufnahme\". Fotoalbum mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-34-1-150x150.jpg",
                "position": 29,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19068",
                "visible": true,
                "label": "Seite 36",
                "nav_title": "Seite 36",
                "caption": "Seite 36 des Albums mit der Beschriftung \"Glühsender für Einzelpumpstände der Bildröhrenfertigung (Obj. VI/9)\". Fotoalbum mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-36-1-150x150.jpg",
                "position": 30,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19069",
                "visible": true,
                "label": "Seite 37",
                "nav_title": "Seite 37",
                "caption": "Sockelautomat für Bildröhren (Obj. IX, 8a), Seite 37 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-37-1-150x150.jpg",
                "position": 31,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19070",
                "visible": true,
                "label": "Seite 38",
                "nav_title": "Seite 38",
                "caption": "Prüffeld der Bildröhrenfertigung (Obj. 6), Seite 38 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-38-1-150x150.jpg",
                "position": 32,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19071",
                "visible": true,
                "label": "Seite 39",
                "nav_title": "Seite 39",
                "caption": "Prüfgerät für Bildröhren (VI/4) , Seite 39 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-39-1-150x150.jpg",
                "position": 33,
                "source_item_id": "0"
              },
              {
                "source_kind": "wp_media",
                "source_id": "19072",
                "visible": true,
                "label": "Seite 40",
                "nav_title": "Seite 40",
                "caption": "Regale im Prüffeld der Bildröhrenfertigung (Obj. I/9) , Seite 40 eines Fotoalbums mit 41 Fotos von der Produktion aus dem Werk für Fernmeldewesen (HF), 1951, mit deutscher und russischer Beschriftung.",
                "caption_override": "",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/ALB-003-40-1-150x150.jpg",
                "position": 34,
                "source_item_id": "0"
              }
            ]
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 21104,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "14060"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "b3e0d72e709746a55e3d37e3b50731e472ca289702168c6b519f79434a03e189",
        "post_content": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_title": "79ef464b127124298f88fc3ff16f35d5fe3b2badd9c542b6232fecdc536490cd",
        "post_excerpt": "cfea958e10f204a7e535a22598011cacbcf1e0c2c47793e1ca9e3df613378a4d",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "cba91d3b3e7552504fa3787e471953b771935f032d6f3b25551ee09f4a0a4f72",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "a7f767b882013d0663d030d20c96054d48bc14cbd06df2165ff28c1dc41a5bf4"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "geschichte-des-wf"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 21105,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "14060"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          0
        ],
        "_iss_publication_price_cents": [
          0
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "b3e0d72e709746a55e3d37e3b50731e472ca289702168c6b519f79434a03e189",
        "post_content": "dca2ceb19922494804ab8b220b4566c8c1937c885565a23b5417d348a88db889",
        "post_title": "18cd53d5ee8c54106feb9383224add18c7e47b0f27f13599fcf1cfc9d0594da9",
        "post_excerpt": "cfea958e10f204a7e535a22598011cacbcf1e0c2c47793e1ca9e3df613378a4d",
        "post_status": "7743ce348d9284d677a185f33295b92266cc435a5b5f775029b300066d26693a",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "ff8ef9027f9f6896357266291fc2169a5a59997eb15bc9b70c34847a60fd2c0b",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "a7f767b882013d0663d030d20c96054d48bc14cbd06df2165ff28c1dc41a5bf4"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "geschichte-des-wf-eine-entwicklungsgeschichte"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "longread-poster",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Lesepfad",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Inhalt",
            "body": "<p class=\"wp-block-paragraph\">Das LKVO, die Keimzelle des späteren Werks für Fernsehelektronik (WF) In der Ostendstraße 1-5 hatten sich zu Ende des 2. Weltkriegs mehrere Elektrofirmen befunden. Telefunken betrieb hier seit 1936 eine Servicestelle für Rundfunksender und –empfänger, AEG hatte Ende der 1930er Jahre auf dem Gelände die…</p>",
            "anchor": "inhalt",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 21108,
      "new": false,
      "post": {
        "post_excerpt": ""
      },
      "meta": {
        "_thumbnail_id": [
          "14134"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "1"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "16ddabfaa7a5c2f6d9be60945f5a723e19603cb44bd765a3869ecb1180bb2495",
        "post_content": "47e438bc3576b7c2ae721183352a09e8f711286bc320b9c1e2a56cc125f85258",
        "post_title": "1ccdb392e9f6f536314cf5a3a935e3e8479580b1642e9ea675d6c32b9500051d",
        "post_excerpt": "93ab2e5cebd153f3541671ba97d0302e17a4a4101f80a2ad7dbe8ca654a4468c",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "19ea63f6e22e25f0ed44b09f673eac7b12e50c0dc41798699f8b93222849407c",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "1e9fb0a158a8d9907ec9dc64cabc8729a5bc58308229c4d88ffdf13f8e0377a0"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [
          "4b19366ecde53363e1ff66907608ad9dfe0959de35929d1ddc30020ee3f11b0a"
        ],
        "_iss_editorial_enabled_ausstellung": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "rohren-fur-die-republik"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "quellenbuehne",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "leitfrage",
            "kicker": "Ausstellung",
            "title": "Röhren für die Republik",
            "body": "Eine Technikgeschichte des Werkes für Fernmeldewesen und des Werkes für Fernsehelektronik in Oberschöneweide. Die 50-teilige Archivreihe wird hier nicht nacherzählt, sondern zu einem Lesepfad verdichtet: vom sowjetischen Neubeginn über Sender-, Empfänger-, Gasentladungs- und Bildröhren bis zur Halbleiterfertigung."
          },
          {
            "type": "facts",
            "kicker": "Korpus",
            "title": "50 Folgen. Lokal gesichert sind derzeit 35.",
            "body": "<strong>35</strong> veröffentlichte Archivbeiträge sind lokal vorhanden: Folgen 11-13, 18-48 und 50.\n\n<strong>15</strong> Folgen fehlen im lokalen Korpus: 1-10, 14-17 und 49.\n\n<strong>1</strong> kuratorische Entscheidung: Die Ausstellung folgt Themen und Umbrüchen, nicht der alten Folgenzählung.",
            "facts": []
          },
          {
            "type": "zitat",
            "kicker": "1 · Neubeginn",
            "title": "Aus Reparationsproduktion wird ein volkseigener Betrieb.",
            "body": "Die Geschichte beginnt nicht bei einem fertigen DDR-Produkt, sondern bei einem Standortwechsel der Macht. Das frühere Röhrenwissen der Oberspreewerke wurde nach 1945 in sowjetische Aufträge, Reparationsleistungen und neue Betriebsformen übersetzt. Die TS 41 steht als frühes Objekt für diese Übergangszeit.",
            "quote": "Eine Schautafel von 1953 erklärt die Kurzwellen-Sendetriode TS 41 auf Deutsch und Russisch.",
            "attribution": "Folge 24, lokal gesicherter Archivbeitrag 14063",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "14065",
                "label": "Schautafel zur Kurzwellen-Sendetriode TS 41, 1953",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/02/500w_kurzwellen-sendetriode-ts-41-informationstafel-foto-1953-85808-259x300.jpg",
                "width": "500",
                "height": "580"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-left"
          },
          {
            "type": "zitat",
            "kicker": "2 · Infrastruktur",
            "title": "Senderöhren bauten Reichweite.",
            "body": "Senderöhren erzählen vom Werk als Teil technischer Infrastruktur. Sie stecken hinter Rundfunk, Nachrichtentechnik und Fernsehen. Die Reihe zeigt mehrere Linien: frühe wassergekühlte Senderöhren, Gruppenfotos strahlungsgekühlter Typen und die SRL 458 für die Fernsehbänder IV und V.",
            "quote": "Am Zweiten Programm des DDR-Fernsehens hing auch die Frage, welche Senderöhren im Land entwickelt und gefertigt werden konnten.",
            "attribution": "Folgen 13, 18, 45 und 46",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13941",
                "label": "Gruppenfoto strahlungsgekühlter Senderöhren, 1959",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/07/TFA-NEG-5918595-300x225.jpg",
                "width": "800",
                "height": "601"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-right"
          },
          {
            "type": "galerie",
            "kicker": "3 · Familien",
            "title": "Die Röhrenfamilien werden lesbar.",
            "body": "Empfängerröhren, Dekatrons, Nixies, Relaisröhren und Thyratrons sind keine Einzelkuriositäten. Als Gruppe zeigen sie, wie breit das Produktions- und Entwicklungsfeld des WF war: Empfang, Anzeige, Zählung, Schaltung, Steuerung und Messung.",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "14094",
                "label": "Empfängerröhre EL 81 mit System und Maßstab, 1960",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/01/137645415_218916513202359_1383359686017644437_n-300x216.jpg",
                "width": "800",
                "height": "577"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "14080",
                "label": "Zwei Zählröhren, sogenannte Dekatrons, 1961",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/02/TFA-NEG-6119853-300x220.jpg",
                "width": "800",
                "height": "587"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13991",
                "label": "Nixie-Röhre Z 5700 M, 1968",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/05/z-5700-m-system-mit-angebrachtem-glaskolben-foto-mai-1968-61330-221x300.jpg",
                "width": "735",
                "height": "1000"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13961",
                "label": "Arrangement mit 12 Gasentladungsröhren",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/06/TFA-NEG-6826812-219x300.jpg",
                "width": "800",
                "height": "1098"
              }
            ],
            "gallery_layout": "sequence"
          },
          {
            "type": "zitat",
            "kicker": "4 · Fernsehen",
            "title": "Bildröhren machten Fernsehen sichtbar.",
            "body": "Mit der 23 LK 1 b und der B 30 M1 verschiebt sich die Geschichte vom Spezialbauteil zum Massenmedium. Die Bildröhre verbindet die Werkbank in Oberschöneweide mit dem Wohnzimmer, mit sowjetischen Aufträgen, dem Sachsenwerk Radeberg und der Entwicklung eigener DDR-Fernsehgeräte.",
            "quote": "Bildröhren waren auch für die Republik bestimmt, aber die frühe Linie führte stark über sowjetische Aufträge.",
            "attribution": "Folgen 25, 47 und 48",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13935",
                "label": "Bildröhre 23 LK 1 b",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/08/Bildroehre-300x222.jpg",
                "width": "1000",
                "height": "740"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-left"
          },
          {
            "type": "facts",
            "kicker": "Maßstab Leningrad T2",
            "title": "Aus einem Fernseher wird eine Produktionsrechnung.",
            "body": "<strong>31</strong> Röhren gehörten zur Bestückung des Fernsehgeräts Leningrad T2; dazu zählte die Bildröhre 23 LK 1 b.\n\n<strong>ca. 65.000</strong> Apparate wurden von 1950 bis 1953 im Sachsenwerk Radeberg hergestellt.\n\n<strong>ca. 200</strong> Apparate blieben laut Beitrag in der DDR; der Rest ging als Reparationsleistung an die Sowjetunion.\n\n<strong>ca. 64.800</strong> Apparate waren damit Reparations-/Exportstücke. Das ist aus der Beitragsangabe errechnet.\n\n<strong>ca. 2.015.000</strong> Röhren ergibt die 31er-Bestückung über 65.000 Apparate gerechnet. Das ist eine abgeleitete Größenordnung, keine eigene Produktionsstatistik im Beitrag.",
            "facts": []
          },
          {
            "type": "galerie",
            "kicker": "Fernsehgerät",
            "title": "Aus Röhren wurde ein Bild. Aus Bauteilen wurde Alltag.",
            "body": "Die Ausstellung nutzt das Fernsehen als öffentliche Schwelle: Hier wird technische Spezialfertigung für Besucher unmittelbar verständlich.",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13931",
                "label": "Fernseher ohne Gehäuse, Foto 1954",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/08/TFA-FOT-5510625-300x225.jpg",
                "width": "800",
                "height": "599"
              }
            ],
            "gallery_layout": "viewport"
          },
          {
            "type": "zitat",
            "kicker": "5 · Forschung",
            "title": "Nicht jede Röhre wurde eine Röhre für die Republik.",
            "body": "Die Reihe ist besonders stark, wenn sie nicht nur Erfolge zeigt. Ignitron, VRL 352, Konkurrenzprodukte, Nachentwicklungen und nicht digitalisierte Forschungs- und Entwicklungsberichte machen sichtbar, wie Forschung, Dokumentation und Produktionsentscheidung zusammenhingen.",
            "quote": "Eine Spur im Findbuch ist noch kein Serienprodukt. Manchmal bleibt sie ein Versuch, ein Foto, ein Datenblatt oder eine offene Frage.",
            "attribution": "Folgen 28, 30, 31, 33, 34 und 44",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "14015",
                "label": "Magnetron 2J26, 1957",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/04/magnetron-2-j-2-g-foto-1957-85743-232x300.jpg",
                "width": "742",
                "height": "960"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-right"
          },
          {
            "type": "facts",
            "kicker": "Forschungsakten",
            "title": "Die Zahlen zeigen auch Lücken.",
            "body": "<strong>23</strong> vorhandene Forschungs- und Entwicklungsberichte beschäftigen sich mit Magnetrons; nur zwei davon sind laut Beitrag digitalisiert.\n\n<strong>20</strong> Forschungs- und Entwicklungsberichte zu Orthikons und Superorthikons sind im Archiv vorhanden; sie warten laut Beitrag alle noch auf Digitalisierung.\n\n<strong>15</strong> strahlungsgekühlte Senderöhren listet das RFT-Handbuch Senderöhren von 1969; nur sechs davon sind auf dem Gruppenfoto von 1959 sicher abgebildet.\n\n<strong>1968</strong> waren von sechs fotografierten Thyratron-Typen nur noch drei im Angebot.",
            "facts": []
          },
          {
            "type": "zitat",
            "kicker": "6 · Kamera",
            "title": "Aufnahmeröhren führten vom Studio in die Objektüberwachung.",
            "body": "Orthikon und Superorthikon markieren eine zweite Fernsehgeschichte: nicht die Bildröhre im Empfänger, sondern die Röhre in der Kamera. Der lokale Bestand verweist auf Entwicklungsberichte, die noch nicht digitalisiert sind. Gerade deshalb eignet sich diese Station als sichtbarer Arbeitsstand des Archivs.",
            "quote": "Die Entwicklung und Fertigung von Bildaufnahmeröhren war ein kleiner, aber wichtiger Bereich im großen Werk.",
            "attribution": "Folge 38 und WF-Kontext",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13967",
                "label": "Findbucheintrag und Foto zum Orthikon F7,5 M2, 1960",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/05/findbucheintrag-orthikon-f-75-m3-foto-31-mai-1960-65229-220x300.jpg",
                "width": "733",
                "height": "1000"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-left"
          },
          {
            "type": "fliesstext",
            "kicker": "7 · Werkstatt des Wissens",
            "title": "Die Fotostelle ist selbst eine Quelle.",
            "body": "Viele Folgen beginnen bei einem Fotoauftrag, einem Findbucheintrag, einem Datenblatt oder einem Forschungs- und Entwicklungsbericht. Die Ausstellung sollte diese Materialität zeigen: nicht nur das Produkt, sondern den Weg, auf dem es heute wieder lesbar wird.\n\nDadurch kann die Ausstellung offen mit Unsicherheit umgehen. Wo Typenbezeichnungen unlesbar sind, wo ein Besteller nur vermutet werden kann oder wo ein Bericht noch nicht digitalisiert ist, wird Archivarbeit als Teil der Erzählung sichtbar."
          },
          {
            "type": "zitat",
            "kicker": "8 · Halbleiter",
            "title": "Am Ende steht nicht die Röhre, sondern der Übergang.",
            "body": "Die letzte lokal gesicherte Folge führt zu Halbleiter-Dioden und zur Diodenfertigung. Das ist kein Nachtrag, sondern der Schlusspunkt der Ausstellung: Das WF blieb nicht einfach Röhrenwerk. Neben und nach den Elektronenröhren wurden Halbleiter, optoelektronische Bauelemente und neue Produktionsbereiche wichtig.",
            "quote": "Während die Empfängerröhrenherstellung 1968 eingestellt wurde, expandierte die Diodenfertigung in den 1970er Jahren weiter.",
            "attribution": "Folge 50, lokal gesicherter Archivbeitrag 13924",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13926",
                "label": "Halbleiter-Diode OA 685 mit Verpackung, 1958",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/08/halbleiter-diode-oa685-mit-verpackung-foto-mai-1958-60178-300x236.jpg",
                "width": "800",
                "height": "630"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-right"
          },
          {
            "type": "facts",
            "kicker": "Übergang",
            "title": "Röhren, Bildröhren, Halbleiter.",
            "body": "<strong>1957</strong> wurde im Bauteil A eine eigene Abteilung für Dioden- und Halbleiterfertigung eingerichtet.\n\n<strong>1960</strong> wurden vor allem Bildröhren zum wichtigen Produkt neben den Elektronenröhren.\n\n<strong>1961</strong> war die Halbleiterfertigung neben dem Röhrenwerk ein eigener Werkteil.\n\n<strong>1968</strong> wurde die Empfängerröhrenherstellung eingestellt; die Diodenfertigung expandierte in den 1970er Jahren weiter.",
            "facts": []
          },
          {
            "type": "kapitel",
            "kicker": "Ausstellungsentscheidung",
            "title": "Diese Fassung ist eine Verdichtung, keine Folgenliste.",
            "body": "Die alte Serie bleibt als Quellkorpus erhalten. Die Ausstellung reduziert sie auf acht thematische Stationen und eine offene Archivnotiz. Wenn die fehlenden Folgen 1-10, 14-17 und 49 wieder gesichert sind, sollten sie nicht automatisch als neue Stationen angehängt werden. Zuerst ist zu prüfen, welche bestehende Station sie stärken oder korrigieren.",
            "links": [
              {
                "label": "Archiv durchsuchen",
                "url": "/archiv/?s=Röhren%20für%20die%20Republik"
              },
              {
                "label": "Technikgeschichte lesen",
                "url": "/publikationen/rohren-fur-die-republik-eine-technikgeschichte/"
              },
              {
                "label": "Elektrotechnik im WF",
                "url": "/ausstellungen/elektrotechnik-im-wf/"
              }
            ],
            "section_treatment": "aside"
          },
          {
            "type": "schluss",
            "kicker": "Schluss",
            "title": "Röhren waren Bauteile. Im WF wurden sie zu Infrastruktur, Planwirtschaft und Erinnerung.",
            "body": "Die Reihe zeigt eine Industriegeschichte im Kleinen: ein Bauteil, ein Foto, ein Datenblatt, ein Betrieb und eine Republik, die ihre technische Souveränität immer wieder neu behaupten musste. Die komprimierte Ausstellung hält diese Spannung offen: Produktgeschichte, politische Ökonomie und Archivarbeit gehören zusammen.",
            "links": [
              {
                "label": "Archivbestand",
                "url": "/archiv/?s=Röhren"
              },
              {
                "label": "WF-Ausstellungen",
                "url": "/ausstellungen/elektrotechnik-im-wf/"
              },
              {
                "label": "Publikationen",
                "url": "/publikationen/"
              }
            ]
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 21109,
      "new": false,
      "post": {
        "post_status": "publish"
      },
      "meta": {
        "_thumbnail_id": [
          "14134"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "0"
        ],
        "_iss_publication_price_cents": [
          "0"
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "16ddabfaa7a5c2f6d9be60945f5a723e19603cb44bd765a3869ecb1180bb2495",
        "post_content": "d3e6f7c84f34b8872b5806793c6a524f200474a6d5754cc5cebf9d26a40939d4",
        "post_title": "11b1c1e403221d4eb121b3953106c38d3f3a8931509eacdb7bbc763139169123",
        "post_excerpt": "93ab2e5cebd153f3541671ba97d0302e17a4a4101f80a2ad7dbe8ca654a4468c",
        "post_status": "7743ce348d9284d677a185f33295b92266cc435a5b5f775029b300066d26693a",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "e6ccbdd32d034de4d6b74df5396720d34e49fd5424ffa11370b0c663200fbea2",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "1e9fb0a158a8d9907ec9dc64cabc8729a5bc58308229c4d88ffdf13f8e0377a0"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "rohren-fur-die-republik-eine-technikgeschichte"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "longread-poster",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p>Der derzeit verfügbare Korpus versammelt die lokal gesicherten Folgen der Reihe. Fehlende Teile werden nach weiterer Prüfung ergänzt.</p>"
          },
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Lesepfad",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Lesepfad",
            "body": "<p class=\"wp-block-paragraph\">Diese erste Fassung versammelt die derzeit lokal gesicherten Folgen der Reihe <em>Röhren für die Republik</em>. Fehlende Folgen gelten vorerst als offen und werden nach weiterer Prüfung ergänzt.</p>",
            "anchor": "lesepfad",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 21110,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "13889"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "0"
        ],
        "_iss_publication_price_cents": [
          "0"
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "de6827d2cf77f7e4c16958113c420f2d229dfc32460911c6bb19eae2cedb646c",
        "post_content": "a027f1c483599752406f51c382079df5da0fe0aa41b41ca8672fdf3f10fce507",
        "post_title": "18777317d66f65b1a3c512351050fcb9a0076d1fb14356b06dd01b167fc32122",
        "post_excerpt": "8a802520255070ec1bbad7a2cf43bc4a4fb248128714305947bb9e86113384d3",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "7d0cc30c55611cffdb671556abec5ccbb3969927bb8339d36d0d732d1175e651",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "95ecb05c93defc3899ec919ffba63138f8df897884221a664bace3dea442d4fc"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_publication_price_cents": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "farbfernsehen-in-der-ddr-schon-vor-dem-mauerbau"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "longread-poster",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Lesepfad",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Farbfernsehen in der DDR schon vor dem Mauerbau?",
            "body": "<p class=\"wp-block-paragraph\"><strong>von Peter Salomon</strong></p><p class=\"wp-block-paragraph\">Allgemein bekannt ist, dass offiziell zum 20. Jahrestag der Gründung der DDR das Farbfernsehen in der DDR eingeführt wurde. Ist das wirklich so gewesen, oder gab es dazu nicht schon weit vorher Initiativen?</p><p class=\"wp-block-paragraph\">Weit weniger bekannt ist, dass es bereits vor dem Mauerbau Bestrebungen gegeben hat, die Farbfernsehtechnik in der DDR zu entwickeln. Dem zufolge jährte sich im vorigen Jahr – 2021 – zum 60. Male der erste Versuch das Farbfernsehen in der DDR zu etablieren.</p><p class=\"wp-block-paragraph\">Pandemie-bedingt konnte dieses Ereignis leider nicht in der entsprechenden Form gewürdigt werden.</p><p class=\"wp-block-paragraph\">Hier nun der Bericht dazu, der sich im Wesentlichen auf eine diesbezügliche Artikelserie [1] in der Hauszeitung “WF-Sender“ vom Dezember 1960 des VEB Werk für Fernsehelektronik Berlin (WF) stützt, sowie dem Vortrag „Fernsehtechnik aus Berlin – Teil 3“ vom 23.06.2011, gehalten von Heinz Fuhrmann, ehemals Direktor für Technik der VVB Bauelemente und Vakuumtechnik (BuV).</p><p class=\"wp-block-paragraph\">Initiator dieses spannenden Themas war Dr.-Ing. Peter Neidhard aus dem WF. Neidhardt kam als „SU-Spezialist“ Anfang der 1950er Jahre wieder zurück in die DDR und hatte wie alle anderen (z.B. Ardenne, Thiessen, Hartmann, Falter) besonderes Ansehen als Person und Wissenschaftler. Nur so ist zu erklären, dass er jahrelang in Eigeninitiative ein derartiges F/E-Projekt betreiben konnte, wo doch die Prioritäten seitens Partei und Regierung ganz woanders lagen: Aufbau der Schwerindustrie und Chemisierung in der DDR.</p><p class=\"wp-block-paragraph\">Aufbauend auf den Überlegungen Manfred von Ardenne zum Farbfernsehen aus dem Jahre 1938  (man kannte sich aus der Zeit in der UdSSR) hatte Neidhard nicht nur die funktionsbestimmende Farbbildröhre B43G4C – das Colorskop – entwickelt (ein Exemplar kann im Industriesalon Schöneweide in der ständigen Ausstellung zum WF angeschaut werden), sondern auch die gesamte Elektronik der diesbezüglichen Anlagentechnik.</p><p class=\"wp-block-paragraph\">Die Lochmaske in der Neidhardt’schen Farbbildröhre war zwar nicht neu – die gab es schon bei den amerikanischen RCA-Farbbildröhren, aber die hatten immer noch einen Rundkolben in Metallausführung. Die Farbbildröhre von Neidhardt war dagegen eine Rechteckbildröhre in Vollglas-Ausführung, wie die späteren Schwarz-Weiss-Bildröhren aus dem WF auch.</p><figure class=\"wp-block-image size-medium\"><img src=\"{{SITE_URL}}/wp-content/uploads/2022/05/colorskop-1960-57594-300x224-1.jpg\" alt=\"\" class=\"wp-image-13889\" /><figcaption class=\"wp-element-caption\">Gesamtansicht des Colorskops von der Seite</figcaption></figure><p class=\"wp-block-paragraph\">Als dann 1960/61 die jahrelange Entwicklung erfolgreich mit dem Entwicklungsstand K5 abgeschlossen war, sollte eigentlich der Weg zur Großserienproduktion frei gemacht werden. Grundlage dazu war eine Pilotserien-Fertigung von ca. 100 Stück in einer neuen Fertigungshalle gegenüber dem WF an der Ostendstrasse. Muster-Farbbildröhren wurden auch an alle möglichen Interessenten in der DDR gegeben (ZRF Dresden, VEB Fernsehgerätewerk Staßfurt, RFZ Berlin Adlershof). Keiner zeigte sonderliches Interesse, sich mit diesem zukunftsträchtigen Thema intensiv zu befassen, oder es wurde nur halbherzig damit gearbeitet, weil es keine staatliche Direktive dazu gab. Trotzdem wurde vom WF ein entsprechender Investitions-Antrag über ca. 70 Mio. dem übergeordneten Organ, der VVB BuV vorgelegt. Zur gleichen Zeit sollte aber auch das Halbleiterwerk in Frankfurt/O. aufgebaut werden. Wie sollte die VVB entscheiden? – Zu beiden Vorhaben gleichzeitig reichten die vorhandenen Mittel nicht aus.</p><p class=\"wp-block-paragraph\">Außerdem gab es Bedenken hinsichtlich des Absatzes der Farbbildröhren, wo doch noch nicht abzusehen war, wann in der DDR das Farbfernsehen eingeführt werden sollte. Es fehlte noch jegliche Grundlage – Studiotechnik, Geräteproduktion usw. Auch in den anderen RGW-Ländern war die Situation nicht viel anders. Ein Export in den Westen wäre schon an der Wirtschaftsblockade gescheitert, die der Westen anlässlich des Mauerbaus über die DDR verhängt hatte.</p><p class=\"wp-block-paragraph\">Somit hatten die Verantwortlichen entschieden, die vorhandenen Investitionsmittel für den Aufbau des Halbleiterwerks auszugeben und (zunächst) keine Serienproduktion von Farbbildröhren zu veranlassen. Das bedeutete aber nicht, dass nicht weiter an dem Thema im WF gearbeitet wurde – obwohl Neidhardt aus verständlichem Frust darüber dann das WF verlassen hatte.</p><p class=\"wp-block-paragraph\">Vorliegende F/E-Berichte zeigen, dass noch bis Mitte der 1960er Jahre weiter am Thema „Farbbildröhre“ entwickelt wurde. Immer mal wieder wurde auch seitens des Entwickler-Kollektivs ein Anlauf genommen, doch noch die Serienproduktion beginnen zu können.</p><p class=\"wp-block-paragraph\">Als dann aber seitens Partei und Staatsführung der DDR festgelegt wurde, dass 1969 zum Jahrestag der DDR das Farbfernsehen eingeführt werden sollte, stand wieder das Problem „Farbbildröhre“ auf der Tagesordnung. Nun gab es aber ein Angebot der UdSSR, die Bedarfsdeckung an Farbbildröhren für die DDR vornehmen zu können. Die Farbbildröhren-Produktion in der UdSSR basierte auf einem Abkommen mit Frankreich (de Gaulle hatte den NATO-Austritt Frankreichs betrieben und war somit der UdSSR „näher gekommen“), das SECAM-System für die UdSSR und den gesamten Ostblock übernehmen zu wollen.</p><p class=\"wp-block-paragraph\">Leider wurden dann auf hoher Regierungsebene vorschnell Vereinbarungen abgeschlossen, die in keiner Weise mit den technischen Bedingungen in der DDR (TGL-Normen) übereinstimmten. Somit wurden die Farbbildröhren nach GOST (Norm in der UdSSR) geliefert, von denen ca. 30 % für den Einsatz im „Color 20“ und den weiteren Farbfernsehempfängern aus Staßfurt ungeeignet waren. Tausende diese Ausschussröhren liegen heute noch in alten Kali-Schächten bei Staßfurt.</p><p class=\"wp-block-paragraph\">Diese Situation änderte sich erst, als nach der Honecker-Reise nach Japan u.a. auch eine Fertigungstechnologie für Schlitzmasken-Farbbildröhren von Toshiba gekauft werden konnte, mit der dann erfolgreich viele Jahre im WF Farbbildröhren nach dem modernen Schlitzmasken-Prinzip gefertigt wurden. Auch darüber gab es im Industriesalon Schöneweide im Rahmen der Vortragsserie „Fernsehtechnik aus Berlin – Teil 4“ den Vortrag „Das WF-Farbbildröhrenwerk – Neue Technologie“, gehalten am 29.11. 2011 von Helmut Meinke, letzter Geschäftsführer des an SAMSUNG gegangenen Werksteil des WF.</p><p class=\"wp-block-paragraph\"><u>Literatur</u></p><ul class=\"wp-block-list\">\n<li>Artikelserie von Ing. Peter Neidhard in der Hauszeitung des VEB Werk für Fernsehelektronik Berlin (im Archiv und der Mediathek des Industriesalon Schöneweide vorhanden)</li>\n<li>Vortragsreihe „Fernsehtechnik aus Berlin“ im Industriesalon Schöneweide, Teil 3 – „DDR-Farbfernsehen – 1. Generation“ (Skript in der Mediathek des Industriesalon)</li>\n<li>Zum Problem des Farbfernsehens: Teil 1: Grundsätzliche Überlegungen über die Auflösung farbiger Fernsehbilder, Teil 2: Über die Wirkung der Schärfenabnahme bei mit gleichem Frequenzband… Telegraphen-Fernsprech-Funk-und Fernsehtechnik, Heft 7, 264, 1938</li>\n</ul><p class=\"wp-block-paragraph\">© Copyright by Peter Salomon, Berlin – März 2022</p>",
            "anchor": "farbfernsehen-in-der-ddr-schon-vor-dem-mauerbau",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 21111,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "13884"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "0"
        ],
        "_iss_publication_price_cents": [
          "0"
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "de6827d2cf77f7e4c16958113c420f2d229dfc32460911c6bb19eae2cedb646c",
        "post_content": "c6db685fdd592b50a9452f9815af6ce0b07a4c6a4d0389072ced1b52dd37d035",
        "post_title": "10052ac3ce6223b6281b07c215cd3713d67a12f83fa6aaea8e749fbadd857689",
        "post_excerpt": "4d2780ae870f55af67f95c3f8f47cbc3f8d15aeb87196bfe235e9bfe0718fa69",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "b588bb626bf6206cef21a3396b45603f4ab11cb9f47c376655c641108d0192fb",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "493f62566183858db130995b854fb7f1f104cee06ae25939232e746f83152dce"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_publication_price_cents": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "fundstucke-zur-geschichte-des-nef-im-archiv-des-industriesalons"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "longread-poster",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Lesepfad",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Was wurde im NEF produziert?",
            "body": "<p class=\"wp-block-paragraph\">Sowohl im Landesarchiv als auch 2010 in den Industriesalon geretteten Beständen aus dem WF finden sich kaum Informationen zur Entwicklungstätigkeit und Produktion des NEF in den ersten Jahren des Bestehens. Es gibt keine Geschäftsberichte, obwohl die im NEF mit Sicherheit auch verfasst wurden, genau wie im LKVO – von dem die Geschäftsberichte aus den frühen Jahren erhalten geblieben sind.</p><p class=\"wp-block-paragraph\">Im Gegensatz zum LKVO/OSW gibt es auch keine Forschungs- und Entwicklungsberichte, obwohl die bestimmt auch im NEF geschrieben wurden. Ab Mitte der 1950er Jahre wurden innerhalb des Werks für Fernmeldetechnik fast alle Produktions- und Forschungszweige, die aus dem NEF hinzugekommen waren, ausgegliedert. Vermutlich wurden die FEBs mit an die Werke, z.B. das Funkwerk Köpenick, gegeben, die die jeweiligen Produktionen weiterführten.</p><p class=\"wp-block-paragraph\">Zu einem großen Teil erhalten geblieben ist aber das frühe Negativ-Archiv des NEF mit den – erst 1952 vergebenen – Negativnummern 1000-1200. &nbsp;Wie schon berichtet, wurden um 1952 die Negativbestände, die aus den ins Werk für Fernmeldetechnik integrierten Werken stammten, sortiert und ab Februar 1953 ein Findbuch geführt. Für die Nummern 1000-7999 gab es kein Findbuch, was vermutlich dazu führte, dass die meisten frühen Negative aus dem NEF bei der Ausgliederung der Werkteile im Fotoarchiv blieben.</p><p class=\"wp-block-paragraph\">Leider ist dieser Teil des Fotoarchivs, der zu einem großen Teil aus Glasnegativen besteht, noch nicht digitalisiert, aber die Negativtaschen geben Hinweise darauf, was im NEF von 1946 bis 1952 entwickelt und produziert wurde. Bis 1952, weil trotz des Zusammenschlusses von NEF und OSW 1950 jedes Werkteil bis 1952 noch relativ selbständig weiterarbeitete.</p><p class=\"wp-block-paragraph\">Offensichtlich wurden vor allem Prüf- und Messgeräte entwickelt und hergestellt. So befinden sich unter den Negativen jeweils mehrere mit der Taschenbeschriftung: &nbsp;Messbrücke, Windungsgsschlussprüfer, Messbrücke für Widerstandstoleranz, Hochspannungsprüfgerät, Magnetflussmesser, Wechselsinusprüfgerät, Messwinkel-Messplatz, Scheinleitwertmessbrücken, Lautstärkemesser, Phasenmesser, Messempfänger (LU-6964-701).</p><p class=\"wp-block-paragraph\">Ausführlich fotografiert wurden der Leistungssender 20-300 Khz (Type 06-15002) und ein Überlagerungsempfänger. Auch verschiedene Verstärker finden sich unter den Negativen: Anzeigeverstärker (=6.91001), Grundverstärker (V12 LU 15080/2), Leistungsverstärker (LU 15460), Fotozellenverstärker (LU 15480)</p><p class=\"wp-block-paragraph\">Ferner wurde eine Rufumsetz-Einrichtung mit Fotos dokumentiert und Teile einer Fernsteueranlage wie Wählergerät, Zwischenrelaisrahmen, Zwischenkabel. Auch an einer Neuentwicklung des Fernschreibers wurde den Negativen zufolge gearbeitet.</p><p class=\"wp-block-paragraph\">14 Fotos mit der Aufschrift „Eingelagerter Telegrafiekanal“ deuten auf eine umfangreichere technische Dokumentation hin.</p><p class=\"wp-block-paragraph\">Das in diesem Beitrag abgebildete Foto stamm aus dem im Sommer 1946 entstandenen NEF-Fotoalbum. Die Bildunterschrift lautet übersetzt: „Baugruppen, die in universellen Systemen arbeiten.“</p>",
            "anchor": "was-wurde-im-nef-produziert",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 21112,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "14134"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "e7f4f836b0f9009570c3e6ea4efd4ee0caf4061be8af514bc84e9503705ada10",
        "post_content": "b5a5a0fa826ca25ec2528f5ebaed380cbca01f65ce792397b787ff02074fc066",
        "post_title": "cc9377dec40125e01397d1ede026c7b48ab252060e1eff95ff84ee67a9ec26f5",
        "post_excerpt": "2a4b5a6b899617258f6d83e991ae9642fda3e970da1ae406929076d7dcd03b2b",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "16590b5e767980af55d540d501bc25077c6abf9bb4a622b1b549f17cb5a64481",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "1e9fb0a158a8d9907ec9dc64cabc8729a5bc58308229c4d88ffdf13f8e0377a0"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "elektrotechnik-im-wf"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 21113,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "13923"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "3dd9c03da168a7905c72dc5d86a1a12843e9ed9b18ae5fafe04c2bb871ed3621",
        "post_content": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_title": "3466d9a1576e8e78d4a70f9f588bf59649a1f484188f2269fbdf0a5a84c4191a",
        "post_excerpt": "1c839aea6cc75d57a8563610084fb7601a3ac20c439aa294264b69b8ea0b5254",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "c025e4519626d39d34f57a280518c4e49ab5ebba651f59c5654b25095f0ece0a",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "b982632b89b5b89aa3f7f0f4c1a827b28954dd226497af68c11ebda21ef2c4bf"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "fundstucke-aus-dem-landesarchiv-berlin"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 21114,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "13923"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          0
        ],
        "_iss_publication_price_cents": [
          0
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "3dd9c03da168a7905c72dc5d86a1a12843e9ed9b18ae5fafe04c2bb871ed3621",
        "post_content": "48e765e329ce881e3ea1420e790869286c1455b9cd1568cdd1a281da18be5321",
        "post_title": "1c2748ee3fc7d27b535f86509d2f3f7453a45476c96432c17d42edb472787bee",
        "post_excerpt": "1c839aea6cc75d57a8563610084fb7601a3ac20c439aa294264b69b8ea0b5254",
        "post_status": "7743ce348d9284d677a185f33295b92266cc435a5b5f775029b300066d26693a",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "e62276bf1a34747f87321e85c193d2c22ab401f4d79c2beb3fd55bc6d04dc275",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "b982632b89b5b89aa3f7f0f4c1a827b28954dd226497af68c11ebda21ef2c4bf"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "fundstucke-aus-dem-landesarchiv-berlin-eine-quellengeschichte"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "longread-poster",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Lesepfad",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Inhalt",
            "body": "<p class=\"wp-block-paragraph\">Der Vorgänger des NEF  – die FAO (Fernmeldekabel- und Apparatefabrik Oberspree) Wie schon in einem Beitrag zum Thema „Quellen zur Geschichte des WF“ berichtet, befinden sich im Landesarchiv Berlin in der Rep. C404 diverse Akten zur Geschichte des WF, vor allem aus den frühen Jahren…</p>",
            "anchor": "inhalt",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 21125,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "7569"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "0"
        ],
        "_iss_publication_price_cents": [
          "0"
        ],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "28e457e0ba8f98b3f0fb81f03734fc9ac64be7f5a70e65121f6aec4bb051cfa4",
        "post_content": "a08b39d32cf012e28665577b633e02d85be1a326f4b97607509e987b1bcc0648",
        "post_title": "1e7b8b02305e8ccc214e551125c5d75a0ad2d748595c7dc056609755dc2efe78",
        "post_excerpt": "bb3340ec4b4028c4126ef1722d3294dfe3920bc9c524a0dca2cf848c95482e12",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "fb9282831e8ace80e8a6f32c549895453d2a1f4c191d4aad224694a732c9bacf",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "3f662004cb4676be8aa8eae1a6a2328aeb693df48ceebb81f492e6b34872f4e7",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "acde4087a0dd38f8a7c16fe77c484c9b43dd9b563b3492c4acab804512c166f1"
        ],
        "_wp_page_template": [
          "1080a60800d9e5bddc69f51489c636a776bacaadb5368f46db4aa0947c67e3ba"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_publication_price_cents": [
          "98089e6d36f78e9766c9ea34d5acb3611f3a92cd81c5eb102095d924ffc7d08b"
        ],
        "_iss_entity_key": [],
        "_iss_editorial_publication": [],
        "_iss_editorial_enabled_publication": [],
        "_iss_editorial_publication_skin": []
      },
      "identity": {
        "type": "publication",
        "slug": "schoeneweide-eine-ortsgeschichte"
      },
      "format": "publication",
      "document": {
        "schema_version": 1,
        "skin": "longread-poster",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "<p>Schöneweide wurde nicht einfach industriell genutzt, sondern als Industrielandschaft geplant, gebaut und immer wieder neu gelesen. Die Geschichte des Ortes verbindet AEG, Elektropolis, Krieg, DDR-Industrie, Deindustrialisierung und die heutige Transformation.</p><p>Diese Ortsgeschichte führt Betriebe, Infrastrukturen, Menschen und Brüche zusammen. Sie versteht Schöneweide nicht als Kulisse einzelner Werke, sondern als zusammenhängenden historischen Raum.</p>"
          },
          {
            "type": "publication_rail",
            "kicker": "",
            "title": "Lesepfad",
            "body": "",
            "rail_options": {
              "show_nav": true,
              "show_summary": true,
              "show_related": true,
              "variant": "detailed"
            }
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Vor der Elektropolis",
            "body": "<p class=\"wp-block-paragraph\">Es ist schwer zu glauben, aber Schöneweide war tatsächlich einmal die „schöne Weyde“: eine ausgedehnte Wiese an einer Biegung der Spree, weit entfernt von den Toren Berlins. Im 17. Jahrhundert bildeten sich dort zwei kleine Dörfer an den Spreeufern aus, die Ober- und Niederschöneweide hießen.</p><p class=\"wp-block-paragraph\">Die Spreeschiffer aus Köpenick hielten hier Rast, und allmählich entdeckten auch Berlinerinnen und Berliner das Gebiet als Ausflugsziel. Seit der Mitte des 19. Jahrhunderts entstanden in Ober- wie Niederschöneweide große Ausflugslokale, Musikpavillons, Tanzsäle und erste Fährverbindungen. Noch war Schöneweide kein Industrieort, sondern ein Randbereich zwischen Wasser, Erholung und ländlicher Peripherie.</p><p class=\"wp-block-paragraph\">Vielleicht wäre Schöneweide bis heute ein grüner Erholungsbezirk geblieben, wenn nicht im späten 19. Jahrhundert die industrielle Randwanderung eingesetzt hätte. Mit der Expansion Berlins veränderte sich auch der Blick auf das Spreeufer: Aus Landschaft wurde Standort.</p>",
            "anchor": "vor-der-elektropolis",
            "media_refs": [],
            "media_layout": "inline"
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Die AEG kommt an die Spree",
            "body": "<h3 class=\"wp-block-heading\">Warum Schöneweide?</h3><p class=\"wp-block-paragraph\">Als innerhalb Berlins zunehmend Platzmangel herrschte und großzügige Industriebauten dort kaum noch realisierbar waren, bot Schöneweide genau die Voraussetzungen, die ein expandierendes Unternehmen brauchte: große Flächen, die Spree als Transportweg und seit 1894 eine eigene Industrieanschlussbahn, die die Betriebe untereinander und mit dem Güterbahnhof Niederschöneweide verband.</p><p class=\"wp-block-paragraph\">Eine Grundrentengesellschaft kaufte weite Areale in Schöneweide auf und vermarktete sie gezielt an kapitalkräftige Unternehmer. In diesem Kontext machte Emil Rathenau Schöneweide zu einem Hauptstandort der 1883 gegründeten Allgemeinen Elektricitäts-Gesellschaft. In den 1890er Jahren entstanden hier eine Akkumulatorenfabrik, ein eigenes Kraftwerk, eine Automobilfabrik und ein modernes Kabelwerk mit Kupferwalzwerk, Gummiwerk und Drahtzieherei.</p><p class=\"wp-block-paragraph\">Um 1920 kam die Werkzeugmaschinenfabrik „Deutsche Niles-Werke AG“ hinzu, die binnen weniger Jahre zu einem der größten Transformatorenwerke Europas ausgebaut wurde. Der Aufstieg Schöneweides war damit nicht das Ergebnis einzelner Zufälle, sondern Ausdruck einer gezielten industriellen Raumordnung.</p>",
            "anchor": "die-aeg-kommt-an-die-spree",
            "media_refs": [],
            "media_layout": "inline"
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Die AEG-Stadt",
            "body": "<h3 class=\"wp-block-heading\">Industrie und Stadt als Zusammenhang</h3><p class=\"wp-block-paragraph\">Das Industrieareal in Oberschöneweide gilt als eines der bedeutenden Denkmale der Berliner Industrie und als größtes zusammenhängendes Industriedenkmal Europas. Schöneweide wurde nicht nur von Fabriken besetzt, sondern als zusammenhängende Industrielandschaft geprägt. Produktion, Energieversorgung, Verkehr, Wohnungsbau und soziale Infrastruktur standen in einem funktionalen Verhältnis zueinander.</p><p class=\"wp-block-paragraph\">Die AEG war ein modernes Unternehmen, das auf nahezu allen Gebieten der Elektrotechnik tätig war und das Stadtbild von Oberschöneweide über Jahrzehnte prägte. In der Wilhelminenhofstraße errichtete sie ein langgestrecktes Band von Werksanlagen, das bis heute als gelb verklinkertes Industrieband zwischen Spree und Straße lesbar ist. Daher wird Schöneweide bis heute auch die „AEG-Stadt“ genannt.</p><h3 class=\"wp-block-heading\">Bauen für Produktion und Alltag</h3><p class=\"wp-block-paragraph\">Für ihre Werke verpflichtete die AEG einige der bekanntesten Architekten der Zeit, darunter Franz Schwechten, Peter Behrens sowie die Spezialisten des Industriebaus Paul Tropp und Ernst Ziesel. Das bis heute erhaltene Ensemble aus Stockwerksfabriken, Produktionshallen, Verwaltungsbauten und Wohnhäusern verkörpert die frühe architektonische Moderne und macht Schöneweide zu einem Ort, an dem sich Industriegeschichte räumlich lesen lässt.</p><p class=\"wp-block-paragraph\">Schöneweide war damit nicht nur Produktionsstandort, sondern ein Experimentierfeld industrieller Urbanität. Die Bezeichnung „AEG-Stadt“ benennt genau diesen Zusammenhang: einen Ort, an dem ein Unternehmen nicht nur Gebäude, sondern einen ganzen Stadtraum prägt.</p>",
            "anchor": "die-aeg-stadt",
            "media_refs": [],
            "media_layout": "aside-right"
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Elektropolis und industrielle Moderne",
            "body": "<h3 class=\"wp-block-heading\">Die goldenen Jahre der Elektropolis</h3><p class=\"wp-block-paragraph\">Kein Industriezweig prägte seit Beginn des 20. Jahrhunderts Wirtschafts- und Alltagsleben so tiefgreifend wie die elektrotechnische Industrie. Die europaweit einzigartige Konzentration dieses innovativen Wirtschaftszweiges verhalf Berlin zum Aufstieg zur „Elektropolis“, und Schöneweide wurde zu einem ihrer wichtigsten Standorte.</p><p class=\"wp-block-paragraph\">Die 1920er und frühen 1930er Jahre waren die Blütezeit der AEG. Die Betriebe in Schöneweide zählten zu den wichtigsten Produktionsstätten der deutschen Elektrotechnik. Besonders das AEG-Transformatorenwerk prosperierte mit Großaufträgen wie dem Kraftwerk Klingenberg oder der Erneuerung des japanischen Energienetzes nach dem Kanto-Erdbeben. Auch das Kabelwerk Oberspree brauchte den internationalen Vergleich nicht zu scheuen und war an prestigevollen Infrastrukturprojekten beteiligt.</p><p class=\"wp-block-paragraph\">Mit der „Röhrenfabrik Oberspree“, dem späteren Werk für Fernsehelektronik, kamen weitere technologische Felder hinzu. Schöneweide wurde so zu einem Raum, in dem Werkgeschichte, Innovation, Export und Infrastruktur ineinandergriffen.</p><h3 class=\"wp-block-heading\">Arbeit und soziale Reform</h3><p class=\"wp-block-paragraph\">Bemerkenswert waren auch die Sozialleistungen, die Emil und Mathilde Rathenau in den Betrieben der AEG etablierten. Betriebskantinen, Krankenversicherungen, Fabrikpflegerinnen und frühe Kinderbetreuungseinrichtungen zeigen, dass die industrielle Moderne in Schöneweide nicht nur durch Maschinen, sondern auch durch neue Formen betrieblicher Sozialpolitik geprägt war.</p><p class=\"wp-block-paragraph\">Die Geschichte der Familie Rathenau ist eng mit dem Ort verbunden. Erich Rathenau war erster Direktor des Kabelwerks Oberspree und ließ sich in Schöneweide eine Villa errichten. Walter Rathenau, später Außenminister der Weimarer Republik, wurde 1922 ermordet. Auch der Waldfriedhof Schöneweide, den Emil Rathenau der Gemeinde schenkte, gehört zu dieser Geschichte des Ortes.</p>",
            "anchor": "elektropolis-und-industrielle-moderne",
            "media_refs": [],
            "media_layout": "inline"
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Krieg, Zwangsarbeit und Zerstörung",
            "body": "<p class=\"wp-block-paragraph\">Der Zweite Weltkrieg beendete das goldene Zeitalter der Elektropolis. Nicht mehr technischer Fortschritt oder internationale Handelsbeziehungen bestimmten die Produktion, sondern die Anforderungen der Kriegswirtschaft. Die AEG-Betriebe in Schöneweide stellten auf Rüstungsproduktion um und fertigten unter anderem Munitionshülsen und Flakscheinwerfer.</p><p class=\"wp-block-paragraph\">Da es an Arbeitskräften fehlte, wurden Zwangsarbeiterinnen und Zwangsarbeiter aus vielen Teilen Europas nach Schöneweide verschleppt und in den Betrieben eingesetzt. Sie arbeiteten unter unmenschlichen Bedingungen, zuletzt bis zu 69 Stunden pro Woche und bei minimalen Rationen. Auch KZ-Häftlinge wurden zur Zwangsarbeit herangezogen. Diese Geschichte ist kein Randthema, sondern ein zentraler Bestandteil der Ortsgeschichte.</p><p class=\"wp-block-paragraph\">In der letzten Kriegsphase wurden die Schöneweider Betriebe stark bombardiert. Als beim Rückzug der Wehrmacht im Frühjahr 1945 die Spreebrücken gesprengt wurden, barsten in den Werkhallen fast alle Fenster und Glasdächer. Als die Rote Armee das Industriegebiet besetzte, lagen große Teile der Elektropolis in Trümmern.</p>",
            "anchor": "krieg-zwangsarbeit-und-zerstoerung",
            "media_refs": [],
            "media_layout": "inline"
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Neubeginn in der DDR",
            "body": "<h3 class=\"wp-block-heading\">Neuordnung der Betriebe</h3><p class=\"wp-block-paragraph\">Nach 1945 begann eine neue Ära. Die sowjetische Kommandantur ließ die AEG-Betriebe enteignen und 1946 in die Sowjetische Aktiengesellschaft eingliedern. Unter schwierigsten Bedingungen lief die Produktion langsam wieder an. Zunächst wurden vor allem Gegenstände des täglichen Bedarfs gefertigt, doch bald kamen wieder erste Branchenaufträge hinzu.</p><p class=\"wp-block-paragraph\">Mit dem Aufbau der DDR änderten sich die Eigentumsverhältnisse erneut. Die Werke wurden an die DDR zurückgegeben und firmierten als volkseigene Betriebe. Aus dem Kabelwerk Oberspree, dem Transformatorenwerk Oberschöneweide und dem Werk für Fernsehelektronik entstanden neue Schlüsselbetriebe der DDR-Industrie.</p><h3 class=\"wp-block-heading\">Schöneweide als DDR-Industriestandort</h3><p class=\"wp-block-paragraph\">Um diese Zeit war Schöneweide längst in das System der sozialistischen Planwirtschaft eingebunden. Die Werke waren an Planvorgaben gebunden und konnten nur begrenzt eigenständig agieren. Dennoch entstanden hier elektrotechnische Qualitätsprodukte, die in viele Länder exportiert wurden. KWO, TRO und WF prägten den Ort für Jahrzehnte, wirtschaftlich wie sozial.</p><p class=\"wp-block-paragraph\">Gerade im Werk für Fernsehelektronik zeigte sich jedoch auch die Grenze dieses Modells. Technologische Rückstände, fehlende Devisen und ausbleibende Modernisierung machten die strukturellen Probleme der DDR-Industrie im Laufe der 1980er Jahre unübersehbar.</p>",
            "anchor": "neubeginn-in-der-ddr",
            "media_refs": [],
            "media_layout": "inline"
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Arbeit, Alltag und Sozialstaat im Werk",
            "body": "<h3 class=\"wp-block-heading\">Arbeit im Betrieb</h3><p class=\"wp-block-paragraph\">Die Ortsgeschichte Schöneweides lässt sich nicht nur über Hallen, Produkte und Eigentumsverhältnisse erzählen. Sie lebt ebenso in Werkbänken, Fertigungslinien, Betriebskulturen und dem Erfahrungswissen der Beschäftigten. Gerade hier wird Schöneweide als gelebter Arbeitsort fassbar.</p><h3 class=\"wp-block-heading\">Frauen, Kinder, Versorgung</h3><p class=\"wp-block-paragraph\">Die DDR verstand sich als Sozialstaat der Werktätigen und verlangte von den Betrieben, entsprechende Leistungen vorzuhalten. Zu den Schöneweider Großbetrieben gehörten Polikliniken, Betriebskrippen und -kindergärten, Ferienobjekte, Kinderferienlager und Sporteinrichtungen. Was hier wie eine Nebenlinie wirkt, war in Wahrheit Teil der industriellen Infrastruktur des Ortes.</p><h3 class=\"wp-block-heading\">Kultur und Sozialleben</h3><p class=\"wp-block-paragraph\">Besonders stolz war man auf das kulturelle Angebot. In Kulturhäusern konnte man kegeln, töpfern, schreiben, malen oder batiken, und das KWO besaß sogar ein eigenes Arbeitertheater. Diese sozialen und kulturellen Räume gehören zur Ortsgeschichte ebenso wie die Werkhallen selbst.</p><p class=\"wp-block-paragraph\">Für eine vertiefte Lesart dieses Kapitels sind Publikationen wie <em>Frauen im WF</em> und <em>Kinder im WF</em> ebenso wichtig wie Zeitzeugeninterviews und Archivobjekte, die den Arbeitsalltag sichtbar machen.</p>",
            "anchor": "arbeit-alltag-und-sozialstaat-im-werk",
            "media_refs": [],
            "media_layout": "inline"
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Umbruch nach 1990",
            "body": "<p class=\"wp-block-paragraph\">Mit dem Herbst 1989 und der Wiedervereinigung zeigte sich in vollem Umfang, wie tiefgreifend die Krise der DDR-Wirtschaft war. In Schöneweide, wo sich mit KWO, TRO und WF drei große Elektrobetriebe konzentrierten, bedeutete dies Massenentlassungen, Werksschließungen und den Zusammenbruch einer ganzen industriellen Ordnung.</p><p class=\"wp-block-paragraph\">Weder das KWO unter britischem Eigentümer noch Samsung im WF oder die rückkehrende AEG im Transformatorenwerk konnten sich dauerhaft halten. In den 1990er und 2000er Jahren endete die industrielle Nutzung vieler Bereiche endgültig. Zurück blieben große Hallen, Brachen und Fragmente der Elektropolis.</p><p class=\"wp-block-paragraph\">Gleichzeitig entstanden neue Nutzungen. Start-ups, Ateliers, Kulturorte und später die Hochschule für Technik und Wirtschaft prägten den Wandel. Der Umbruch war nicht nur Verlustgeschichte, sondern auch der Beginn neuer, oft prekärer Formen von Aneignung.</p>",
            "anchor": "umbruch-nach-1990",
            "media_refs": [],
            "media_layout": "inline"
          },
          {
            "type": "longread_chapter",
            "kicker": "",
            "title": "Ort im Wandel heute",
            "body": "<p class=\"wp-block-paragraph\">Heute ist Schöneweide weder reine Industriebrache noch museal stillgestellter Erinnerungsraum. Viele Häuser wurden saniert, am Spreeufer entstanden neue Wege, auf den ehemaligen Werkarealen entwickelten sich Kulturorte, Hochschulnutzungen und neue Unternehmen. Gerade dadurch bleibt der Ort lesbar: als Schichtung von Produktion, Erinnerung und Transformation.</p><p class=\"wp-block-paragraph\">Die Aufgabe der Ortsgeschichte besteht deshalb nicht nur darin, Vergangenes zu sichern. Sie muss auch zeigen, wie stark die Gegenwart noch immer von den räumlichen und sozialen Entscheidungen der Industriezeit geprägt ist. Schöneweide ist kein abgeschlossenes Kapitel, sondern ein Ort, an dem Geschichte weiter verhandelt wird.</p>",
            "anchor": "ort-im-wandel-heute",
            "media_refs": [],
            "media_layout": "inline"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 24815,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "1667"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "4eecff3cd446fd985288db40950340ac8b752e7911b5e66942bda57215ea0185",
        "post_content": "cb49311c89ed6c85e4abbcad783ef8ea8ebe41cc4671562aebc070ffbb3b4931",
        "post_title": "5e788a6b5f18c13cef2421605417c3224c2e0c503db48a732c0a7e6c0ed14cf4",
        "post_excerpt": "a0c20183d900007a79213894633af71d3878144ad2350e303e13be6eb6d72c0e",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "0ac4a63f9031b06a9927a58568ef1fe3d2ada674ff9c85ecaf296a7468352b5a",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "39fa9ec190eee7b6f4dff1100d6343e10918d044c75eac8f9e9a2596173f80c9",
        "post_type": "1e09062a43327744b3f2a8e8496d28968dc502138abb68128da97f80139d3cd5",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "0b8e34998cc235841b334dfe90ef6d28d94331af67199eed9f7068b64fa89a1d"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_projekt": [
          "ef88e77d3a4314125a22117a9ebd504534335a2615be0cdbfdbbcc46a76fad0a"
        ],
        "_iss_editorial_enabled_projekt": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_projekt_skin": []
      },
      "identity": {
        "type": "projekt",
        "slug": "das-landmark-der-elektropolis"
      },
      "format": "projekt",
      "document": {
        "schema_version": 1,
        "skin": "dossier",
        "variant": "standard",
        "features": {
          "rail": {
            "enabled": true
          }
        },
        "sections": [
          {
            "type": "fliesstext",
            "kicker": "",
            "title": "",
            "body": "Das Landmark der Elektropolis war ein zweiphasiger künstlerischer Wettbewerb für den Stadtplatz am Kaisersteg: eine weithin sichtbare Installation, die Schöneweides Industriegeschichte mit regenerativer Energie, Licht und Aufenthaltsqualität verbinden sollte.",
            "links": []
          },
          {
            "type": "galerie",
            "kicker": "",
            "title": "Projektgalerie",
            "body": "",
            "anchor": "projektgalerie",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "1667",
                "label": "Entwurfsdarstellung_klein",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2019/02/Entwurfsdarstellung_klein-1-300x212.jpg",
                "width": "1200",
                "height": "848"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "25267",
                "label": "Berlin_Kaisersteg_Oberschoeneweide_ZfB_1900",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/Berlin_Kaisersteg_Oberschoeneweide_ZfB_1900-230x300.jpg",
                "width": "1967",
                "height": "2560"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "6852",
                "label": "Blick vom Kaisersteg",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2023/03/Blick-vom-Kaisersteg-1-jpg-300x169.webp",
                "width": "2016",
                "height": "1134"
              }
            ],
            "gallery_layout": "grid"
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Ein sichtbarer Energie-Ort für Schöneweide.",
            "body": "Worum es geht\n\nAuf dem zentralen Stadtplatz von Schöneweide sollte ein Landmark entstehen, das die frühere Elektropolis am Spreeufer charakterisiert. Der Anspruch war kein reines Denkzeichen, sondern eine künstlerische Installation mit Fernwirkung, Aufenthaltsqualität und eigener Energieproduktion.\n\nDie Installation sollte Strom erzeugen, speichern und vor Ort nutzbar machen. Damit verbindet das Projekt historische Elektroindustrie, regenerative Energie und den öffentlichen Raum am Kaisersteg.",
            "anchor": "worum-es-geht",
            "links": []
          },
          {
            "type": "facts",
            "kicker": "Fakten",
            "title": "Eckdaten",
            "body": "",
            "anchor": "worum-es-geht-fakten",
            "facts": [
              {
                "value": "Ort",
                "label": "Stadtplatz am Kaisersteg, zwischen Spree, Kranbahn und öffentlichem Uferraum."
              },
              {
                "value": "Energie",
                "label": "Windrotoren, Speicherbatterie und Licht machen Energie als Funktion und Zeichen erfahrbar."
              },
              {
                "value": "Identität",
                "label": "Das Landmark sollte als Hingucker, Identitätsanker und touristischer Einstieg in die Industriekultur wirken."
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Licht, Kupfer, Wind und öffentlicher Raum.",
            "body": "Konzept\n\nDer von der Jury empfohlene Entwurf Lichtgestalt Elektra von Peter Sandhaus interpretiert elektrische Energie abstrakt und konstruktiv. Er greift die Tradition der AEG, das Motiv der Elektra und die Materialgeschichte der Elektroindustrie auf, ohne ein historisches Zeichen direkt nachzubilden.",
            "anchor": "konzept",
            "links": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Wettbewerb, Jury und Fachpartner.",
            "body": "Mitwirken\n\nDer Industriesalon Schöneweide lobte den Wettbewerb in den Jahren 2016 und 2017 aus. Die Realisierung des Wettbewerbs erfolgte in Kooperation mit dem Büro für Kunst im Öffentlichen Raum des Kulturwerks des bbk berlin; das Verfahren orientierte sich an den Richtlinien für Planungswettbewerbe RPW 2013.",
            "anchor": "mitwirken",
            "links": []
          },
          {
            "type": "material",
            "kicker": "",
            "title": "Entwurfsbild und Wettbewerbsdokumentation.",
            "body": "Material\n\nDie Materiallage ist klar begrenzt: ein direkt zugeordneter Entwurfsrender, eine vierseitige PDF-Dokumentation und Ortsfotos vom Kaisersteg. Allgemeine Elektropolis-Fotos aus der Mediathek bleiben Kontext und werden nicht als Wettbewerbsfotos ausgegeben.\n\nDie PDF enthält die Zusammenfassung des Wettbewerbs, die Anforderungen an das Landmark, die Juryempfehlung, das Konzept von Peter Sandhaus sowie die Zusammensetzung von Jury und Sachverständigen.",
            "anchor": "material",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "1014",
                "label": "Infos zum Landmark",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2019/10/Infos-zum-Landmark-pdf-212x300.jpg",
                "width": "0",
                "height": "0"
              }
            ],
            "links": [
              {
                "label": "PDF öffnen",
                "url": "/wp-content/uploads/2019/10/Infos-zum-Landmark.pdf"
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Vom Wettbewerb zur dokumentierten Projektidee.",
            "body": "Rückblick",
            "anchor": "rueckblick",
            "links": []
          },
          {
            "type": "schluss",
            "kicker": "",
            "title": "Fragen zur Wettbewerbsdokumentation.",
            "body": "Kontakt\n\nFür Rückfragen zur Dokumentation, zu historischen Projektständen oder zur Einordnung des Landmarks im Elektropolis-Kontext läuft der Einstieg über den allgemeinen",
            "anchor": "kontakt",
            "links": []
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 24816,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "1620"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "4eecff3cd446fd985288db40950340ac8b752e7911b5e66942bda57215ea0185",
        "post_content": "f435571112cfa31b69e080c4a51ff4e83f81c8a62acf5dd60535cb1e07db503c",
        "post_title": "e2155a4086073c16c7ef3df7071ce7b964b8801525640695c4f3a963cccd6c91",
        "post_excerpt": "be74f45e0099d8372708ff18d62365011280ed14d01215cb9400dc9e002c7fae",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "98aa0f393d470f30178dad87958b7a651af060c0e473db2be727c13a68cb85a2",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "1a6562590ef19d1045d06c4055742d38288e9e6dcd71ccde5cee80f1d5a774eb",
        "post_type": "1e09062a43327744b3f2a8e8496d28968dc502138abb68128da97f80139d3cd5",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "fa504dd664c2791f79dcb3ddd98c06e78ed87e97a71a438a8352c8e80f70f6c5"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_projekt": [
          "a27d1479494444141b073f0382455070b81fbe11514fb493e085bbfc4f7f65b7"
        ],
        "_iss_editorial_enabled_projekt": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_projekt_skin": []
      },
      "identity": {
        "type": "projekt",
        "slug": "connected-by-lights"
      },
      "format": "projekt",
      "document": {
        "schema_version": 1,
        "skin": "dossier",
        "variant": "standard",
        "features": {
          "rail": {
            "enabled": true
          }
        },
        "sections": [
          {
            "type": "fliesstext",
            "kicker": "",
            "title": "",
            "body": "Connected by Lights denkt die Spree als verbindende Bühne: ein künstlerisches Lichtfestival zwischen Berliner Mitte und Altstadt Köpenick, bei dem Ufer, Brücken, Gebäude und Schiffe zu einem gemeinsamen Lichtparcours werden.",
            "links": []
          },
          {
            "type": "galerie",
            "kicker": "",
            "title": "Projektgalerie",
            "body": "",
            "anchor": "projektgalerie",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "2459",
                "label": "connecting_lights",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/04/connecting_lights-300x212.jpg",
                "width": "1200",
                "height": "847"
              }
            ],
            "gallery_layout": "grid"
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Eine leuchtende Verbindung auf der Spree.",
            "body": "Worum es geht\n\nDer Industriesalon engagiert sich für ein künstlerisches Lichtfestival, das Berlinerinnen, Berliner und Gäste einlädt, Treptow-Köpenick vom Wasser aus zu entdecken. Die Ufer der Spree werden nicht als Rand, sondern als Bühne verstanden: Fassaden, Brücken, Hafenkanten, Industriebauten und Schiffe bilden zusammen eine temporäre Galerie.\n\nIm Mittelpunkt stehen vierzehn ausgewählte Wettbewerbsentwürfe. Sie reichen von Projektionen und interaktiven Schattenbildern über leuchtende Textzeichen, Brückeninszenierungen und wiederkehrende Leuchtelemente bis zu Arbeiten, die konkrete Orte der Industriegeschichte aufnehmen.",
            "anchor": "worum-es-geht",
            "links": []
          },
          {
            "type": "facts",
            "kicker": "Fakten",
            "title": "Eckdaten",
            "body": "",
            "anchor": "worum-es-geht-fakten",
            "facts": [
              {
                "value": "14 Entwürfe",
                "label": "Lichtkunst, Brückenlicht und ein verbindendes Leuchtelement aus dem Wettbewerb."
              },
              {
                "value": "Spree-Route",
                "label": "Vom Zentrum Berlins über Treptow und Schöneweide bis in Richtung Altstadt Köpenick."
              },
              {
                "value": "Vom Schiff lesbar",
                "label": "Der Parcours ist für Passagiere gedacht, bleibt aber auch am Ufer sichtbar."
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Route, Wettbewerb, Anrainer.",
            "body": "Konzept\n\nConnected by Lights verbindet drei Ebenen: eine kuratierte Route auf der Spree, einen offenen Wettbewerb für künstlerische Arbeiten und die Einladung an Anrainer, ihre Gebäude und Ufer gemeinsam mit Künstlerinnen und Künstlern zu inszenieren. So entsteht kein einzelnes Objekt, sondern ein zusammenhängender Lichtzusammenhang entlang des Wassers.",
            "anchor": "konzept",
            "links": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Kunst, Ufer, Schiff und Nachbarschaft.",
            "body": "Mitwirken\n\nDas Projekt ist nur als Verbund denkbar. Es braucht künstlerische Entwürfe, geeignete Orte, technische Abstimmung, Eigentümer, Genehmigungen, Schiffsbetrieb und ein Publikum, das die Route als gemeinsames Erlebnis wahrnimmt.",
            "anchor": "mitwirken",
            "links": []
          },
          {
            "type": "material",
            "kicker": "",
            "title": "Vierzehn Wettbewerbsbeiträge als offenes Material.",
            "body": "Material\n\nDie PDF-Anlage dokumentiert die ausgewählten Wettbewerbsbeiträge in drei Teilen. Für die Projektseite werden sie nicht als lose Datei versteckt, sondern als Arbeitsmaterial sichtbar gemacht: Titel, Autorinnen und Autoren, Ortshinweise und Grundidee bleiben auffindbar.\n\nDie Anlage bleibt das vollständige Arbeitsdokument des Wettbewerbs. Die Projektseite zeigt die Beiträge im Überblick; die PDF bewahrt Entwurfsblätter, Reihenfolge und Originalkontext.",
            "anchor": "material",
            "media_refs": [],
            "links": [
              {
                "label": "PDF öffnen",
                "url": "/wp-content/uploads/2020/11/Anlage_Lichtkunstwerke.pdf"
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Ein Wettbewerb, der das Projekt greifbar gemacht hat.",
            "body": "Rückblick\n\nConnected by Lights ist als Vorbereitungsidee und Wettbewerbsstand überliefert. Die Seite hält diesen Stand bewusst fest: nicht als fertiges Festivalprogramm, sondern als ausformuliertes Projektmaterial, das zeigt, welche künstlerische und räumliche Qualität die Route haben könnte.",
            "anchor": "rueckblick",
            "links": []
          },
          {
            "type": "schluss",
            "kicker": "",
            "title": "Projektfragen und Anschluss.",
            "body": "Kontakt\n\nFür Nachfragen zur Wettbewerbsdokumentation, zu möglichen Anschlussformaten oder zu Orten entlang der Route ist der allgemeine Kontakt des Industriesalon der richtige Einstieg.",
            "anchor": "kontakt",
            "links": []
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 24817,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "5443"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "4eecff3cd446fd985288db40950340ac8b752e7911b5e66942bda57215ea0185",
        "post_content": "60b749ddc0084e783117862dbe72c19f9aab4ea89677c882d92beca180a1579d",
        "post_title": "f7387d7858aad3cf55636d2e507d490b37145963045180f0a4fb84a14a2adf2a",
        "post_excerpt": "6f4667dd243a6b8bbc4d30d8b71f063a317826f9bfa6c39c7bf0ba312cc6468d",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "98f8d5b207922a32e359767371fa00726a1aea26bbf56dfe0f85a9daa04304d2",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "f5ca38f748a1d6eaf726b8a42fb575c3c71f1864a8143301782de13da2d9202b",
        "post_type": "1e09062a43327744b3f2a8e8496d28968dc502138abb68128da97f80139d3cd5",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "eeba602dccd1c22b65a4ad02f58e3b03d01412b7736db881b249a30866069f72"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_projekt": [
          "40698bdb1349a17362fde371797fd02e71a7ea7ccd381f6f513140e94ab79ddc"
        ],
        "_iss_editorial_enabled_projekt": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_projekt_skin": []
      },
      "identity": {
        "type": "projekt",
        "slug": "boulevard-der-industriekultur"
      },
      "format": "projekt",
      "document": {
        "schema_version": 1,
        "skin": "dossier",
        "variant": "standard",
        "features": {
          "rail": {
            "enabled": true
          }
        },
        "sections": [
          {
            "type": "fliesstext",
            "kicker": "",
            "title": "",
            "body": "Der Boulevard der Industriekultur ist eine Projektidee für die Wilhelminenhofstraße: Industriegeschichte, öffentliche Orte und aktuelle Transformation sollen zu einer lesbaren Route durch Schöneweide verbunden werden.",
            "links": []
          },
          {
            "type": "galerie",
            "kicker": "",
            "title": "Projektgalerie",
            "body": "",
            "anchor": "projektgalerie",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "5443",
                "label": "Boulevard-Foto1",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2022/06/Boulevard-Foto1-300x201.jpg",
                "width": "709",
                "height": "474"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "10751",
                "label": "klingender_boulevard_website",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2025/07/klingender_boulevard_website-300x300.png",
                "width": "580",
                "height": "578"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "7485",
                "label": "Peter Behrens-Bau-FH-1",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2023/05/Peter-Behrens-Bau-FH-1-jpg-300x204.webp",
                "width": "2048",
                "height": "1393"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "2086",
                "label": "bulle_1",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2021/01/bulle_1-300x212.jpg",
                "width": "842",
                "height": "595"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "7509",
                "label": "2013-01-26_13-01-25-Kaiserstieg-Panorama-02_20x30x300dpi_80%",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2023/05/2013-01-26_13-01-25-Kaiserstieg-Panorama-02_20x30x300dpi_80-1-scaled-1-300x100.webp",
                "width": "2560",
                "height": "853"
              }
            ],
            "gallery_layout": "grid"
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Eine Straße wird als Route lesbar.",
            "body": "Worum es geht\n\nDie Wilhelminenhofstraße erinnert auf den ersten Blick wenig an einen klassischen Boulevard. Gerade darin liegt der Ansatz der Projektidee: historische Produktionsbauten, Läden, Wohnhäuser, Transformationsflächen und Spreeufer sollen nicht als Einzelstücke stehen bleiben, sondern als zusammenhängender Stadtraum erfahrbar werden.\n\nSchöneweide ist ein Schlüsselort der Berliner Elektropolis. Der Boulevard der Industriekultur will diese Geschichte öffnen, in den Alltag holen und mit der laufenden Entwicklung des Quartiers verbinden.",
            "anchor": "worum-es-geht",
            "links": []
          },
          {
            "type": "facts",
            "kicker": "Fakten",
            "title": "Eckdaten",
            "body": "",
            "anchor": "worum-es-geht-fakten",
            "facts": [
              {
                "value": "Straße",
                "label": "Die Wilhelminenhofstraße bildet die Achse zwischen Produktionsgeschichte, Nachbarschaft und neuen Nutzungen."
              },
              {
                "value": "Geschichte",
                "label": "Elektroindustrie, Transformatoren, Röhren, Kabel und Industriebahn werden als Orte im Stadtraum sichtbar."
              },
              {
                "value": "Gegenwart",
                "label": "Start-ups, Wissenschaft, Forschung, Kunst und neue Produktion verändern die früheren Industrieareale."
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Erlebnisorte statt Schautafeln allein.",
            "body": "Konzept\n\nDer Boulevard soll industrielle Vergangenheit nicht nur erklären, sondern im öffentlichen Raum erlebbar machen. Die Quelltexte sprechen von Erlebnisorten entlang der Wilhelminenhofstraße bis zur Spree: kleine Interventionen, experimentelle Stationen, Ausstellungen, Gastronomie und Museumsorte können zusammen eine offene Route bilden.\n\nWie kann sich die Elektropolis als attraktiver Berliner Zukunftsort präsentieren, ohne ihre Industriegeschichte zu glätten? Die Idee ist keine abgeschlossene Bauplanung, sondern ein Rahmen, in dem Eigentümer, Projektentwickler, Anwohnerinnen und lokale Akteure sichtbare Beiträge leisten können.",
            "anchor": "konzept",
            "links": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Ein offenes Konzept braucht lokale Partner.",
            "body": "Mitwirken\n\nDie alten Projekttexte stellen die Boulevard-Idee ausdrücklich als Einladung vor. Der Industriesalon suchte Ideen, starke Partner und Abstimmung mit Menschen, die vor Ort entwickeln, besitzen, arbeiten oder wohnen.\n\nIm Hintergrund steht ein pragmatischer Gedanke: Wenn neue Erlebnisorte die Identität und Attraktivität der Standorte stärken, profitieren auch Eigentümer, Unternehmen und Nachbarschaft.",
            "anchor": "mitwirken",
            "links": []
          },
          {
            "type": "material",
            "kicker": "",
            "title": "Motive, Beispiele und vorhandene Quellen.",
            "body": "Material\n\nDie direkte Materiallage besteht aus den zwei Quellbeiträgen von 2022, dem Boulevard-Projektbild und ergänzenden Medien im Bestand des Industriesalon. Kontextmaterial wird als Kontext eingeordnet, nicht als abgeschlossene Projektdokumentation.",
            "anchor": "material",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "5443",
                "label": "Boulevard-Foto1",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2022/06/Boulevard-Foto1-300x201.jpg",
                "width": "709",
                "height": "474"
              }
            ],
            "links": [
              {
                "label": "Video „Der Bulle“ öffnen",
                "url": "/video/der-bulle-fahrt-im-fuhrerstand-der-industriebahn-durch-oberschoneweide/"
              },
              {
                "label": "Video Farbbildröhrenwerk",
                "url": "/video/das-farbbildrohrenwerk-der-ddr-teil-vom-werk-fur-fernsehelektronik-wf/"
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Konzeptstand aus der Transformationsphase.",
            "body": "Rückblick\n\nDie beiden Quellbeiträge wurden am 20. Juni 2022 angelegt. Sie beschreiben eine vorbereitende Projektidee in einer Phase, in der sich Schöneweide sichtbar veränderte und mehrere Industrieareale neu entwickelt wurden.",
            "anchor": "rueckblick",
            "links": []
          },
          {
            "type": "schluss",
            "kicker": "",
            "title": "Ideen und Rückfragen zum Boulevard.",
            "body": "Kontakt\n\nFür Hinweise, Materialien, Projektideen oder Rückfragen zum Boulevard der Industriekultur läuft der Einstieg über den allgemeinen Kontakt des Industriesalon.",
            "anchor": "kontakt",
            "links": []
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 24818,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "5788"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "4eecff3cd446fd985288db40950340ac8b752e7911b5e66942bda57215ea0185",
        "post_content": "dca1a60019b6c1db5fce45005b43f33cd814896f916b6b0aafa29ace16508276",
        "post_title": "7d7f48f03f0b7fce24e49e29066609894921c3b5f61c95493a56c540b92b6399",
        "post_excerpt": "8b62e5f6863ee3d1f5317d1903e7cef797e205132301212d0ee36dd68068f145",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "12c3cd905181db51b2c976159a6682cf6a19dea524b85a85ecf13d7136ad87a9",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "d59eced1ded07f84c145592f65bdf854358e009c5cd705f5215bf18697fed103",
        "post_type": "1e09062a43327744b3f2a8e8496d28968dc502138abb68128da97f80139d3cd5",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "9986f4dfa540f53c5a6f57c1ea1e25b62089c580ffa7307a370bc308ad4f0e5d"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_projekt": [
          "e35183bf214b7f666c92236316e38b1b0963c1eaef789d13a628bf3de46bde64"
        ],
        "_iss_editorial_enabled_projekt": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_projekt_skin": []
      },
      "identity": {
        "type": "projekt",
        "slug": "stadtlabor-wilhelminenhofstrasse"
      },
      "format": "projekt",
      "document": {
        "schema_version": 1,
        "skin": "dossier",
        "variant": "standard",
        "features": {
          "rail": {
            "enabled": true
          }
        },
        "sections": [
          {
            "type": "fliesstext",
            "kicker": "",
            "title": "",
            "body": "Stadtlabor Wilhelminenhofstraße war eine wachsende Ausstellung und ein Nachbarschaftsexperiment zur wichtigsten Straße Oberschöneweides: Erinnerungen, Bilder, Archivmaterial und Wünsche wurden vor Ort gesammelt und als Ausstellung im Prozess sichtbar gemacht.",
            "links": []
          },
          {
            "type": "galerie",
            "kicker": "",
            "title": "Projektgalerie",
            "body": "",
            "anchor": "projektgalerie",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "5782",
                "label": "SUE_Logo_Stadtlabor_Wortmarke_Rot",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2022/09/SUE_Logo_Stadtlabor_Wortmarke_Rot-300x300.png",
                "width": "2560",
                "height": "2560"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "5783",
                "label": "Stadtlabor_2",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2022/09/Stadtlabor_2-300x191.jpg",
                "width": "1648",
                "height": "1047"
              }
            ],
            "gallery_layout": "grid"
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Die Wilhelminenhofstraße als Lebensnerv von Schöneweide.",
            "body": "Worum es geht\n\nDas Stadtlabor nahm die Wilhelminenhofstraße nicht nur als Verkehrsraum, sondern als verdichteten Erinnerungs- und Industriekorridor in den Blick. Zwischen Ostalgie, Alltag und Neugier wurden Geschichten, Bilder, Informationen und Wünsche gesammelt, die in eine Ausstellung einflossen.\n\nDer Industriesalon brachte dafür sein vorhandenes Fotomaterial zur Geschichte der Straße ein. Besonders wichtig waren Fotodokumentationen aus den Jahren 1962, 1989 und 1992; ergänzt werden sollte eine neue Dokumentation aus dem Jahr 2022.",
            "anchor": "worum-es-geht",
            "links": []
          },
          {
            "type": "facts",
            "kicker": "Fakten",
            "title": "Eckdaten",
            "body": "",
            "anchor": "worum-es-geht-fakten",
            "facts": [
              {
                "value": "Straße",
                "label": "Die Wilhelminenhofstraße wird als historischer, sozialer und gegenwärtiger Stadtraum gelesen."
              },
              {
                "value": "Archiv",
                "label": "Fotodokumentationen aus 1962, 1989 und 1992 bilden den historischen Grundstock."
              },
              {
                "value": "Nachbarschaft",
                "label": "Bewohnerinnen und Bewohner wurden eingeladen, eigene Erinnerungen und Beobachtungen einzubringen."
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Sammeln, zeigen, weiterfragen.",
            "body": "Konzept\n\nDas Projekt war als wachsende Ausstellung angelegt. Es sollte nicht nur fertige Ergebnisse präsentieren, sondern Material aufnehmen, Gespräche auslösen und die Straße Haus für Haus als gemeinsamen Wissensraum öffnen.",
            "anchor": "konzept",
            "links": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Anwohnerwissen, künstlerische Beiträge und historische Vermittlung.",
            "body": "Mitwirken\n\nDas Stadtlabor lebte von Beiträgen aus unterschiedlichen Rollen: Menschen aus der Straße, Künstlerinnen und Künstler, Fotografen, Vermittler und Moderatoren steuerten jeweils eine andere Form von Wissen bei.",
            "anchor": "mitwirken",
            "links": []
          },
          {
            "type": "material",
            "kicker": "",
            "title": "Fotos, Street-Prints, Porträts und Stadtwissen.",
            "body": "Material\n\nDie Materialien zeigen die Wilhelminenhofstraße als Archiv und Gegenwart zugleich. Historische Fotodokumentationen werden mit neuen künstlerischen und nachbarschaftlichen Beiträgen zusammengeführt.\n\nDie Street-Prints verschoben den Blick von der großen Industriegeschichte auf die Oberflächen der Straße. Abdrücke von Steinen, Kanaldeckeln und anderen Dingen machten sichtbar, was im Alltag leicht übersehen wird.",
            "anchor": "material",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "5912",
                "label": "Abdeckung_1 Grafik Albert Markert",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2022/10/Abdeckung_1-Grafik-Albert-Markert-225x300.jpg",
                "width": "1920",
                "height": "2560"
              }
            ],
            "links": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Vom Auftakt zur Akademie für Alles.",
            "body": "Rückblick",
            "anchor": "rueckblick",
            "links": []
          },
          {
            "type": "schluss",
            "kicker": "",
            "title": "Hinweise, Erinnerungen und Material zum Projekt.",
            "body": "Kontakt\n\nWer ergänzende Hinweise, Erinnerungen oder Material zur Wilhelminenhofstraße und zum Stadtlabor hat, erreicht den Industriesalon über den allgemeinen Kontakt.",
            "anchor": "kontakt",
            "links": []
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 24819,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "10634"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "4eecff3cd446fd985288db40950340ac8b752e7911b5e66942bda57215ea0185",
        "post_content": "5137e56345eae5a99d0d9ae002358f95c02f5eeef825fbc06426a17b1c8082b5",
        "post_title": "1774ac43ad419847e9910baf224acd135b3a1789544b4de67cdc942702e6e8b4",
        "post_excerpt": "2b639450ae12d9030a19a74a4c65aec5db8236fb3757e819bc0a2aefec1c77e0",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "4c42b0a3173cdf0bd1e9ec30a45486921e8e4392a5334189129cc4ec35572dd8",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "624b60c58c9d8bfb6ff1886c2fd605d2adeb6ea4da576068201b6c6958ce93f4",
        "post_type": "1e09062a43327744b3f2a8e8496d28968dc502138abb68128da97f80139d3cd5",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "457a5e59ec20c6ac69cd0dba1bbe4356c637d34a77f6c2133c864a2c03d66727"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_projekt": [
          "0ddc7300467b25dfda4a9eb8967313ed3defc8ebeaaff226dd23c2e69489b633"
        ],
        "_iss_editorial_enabled_projekt": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_projekt_skin": []
      },
      "identity": {
        "type": "projekt",
        "slug": "industrieabfaelle"
      },
      "format": "projekt",
      "document": {
        "schema_version": 1,
        "skin": "dossier",
        "variant": "standard",
        "features": {
          "rail": {
            "enabled": true
          }
        },
        "sections": [
          {
            "type": "fliesstext",
            "kicker": "",
            "title": "",
            "body": "Industrieabfälle ist eine Workshop-Reihe zur Industriekultur mit Kindern und Eltern: Geräusche, Stimmen, Fundstücke und Geschichten aus Schöneweide werden gesammelt, bearbeitet und als digitales Radio erfahrbar.",
            "links": []
          },
          {
            "type": "galerie",
            "kicker": "",
            "title": "Projektgalerie",
            "body": "",
            "anchor": "projektgalerie",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "11054",
                "label": "Funkeln-WebsiteII",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2025/12/Funkeln-WebsiteII-300x205.jpg",
                "width": "787",
                "height": "539"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "11058",
                "label": "Industrieabfälle Logo",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2025/12/Industrieabfaelle-Logo.png",
                "width": "300",
                "height": "293"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "11053",
                "label": "INDUSTRIEKULTUR IN SCHÖNEWEIDE Industriekultur ist ein faszinie",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2025/12/industrieabfaelle2025_a3_final-213x300.jpg",
                "width": "560",
                "height": "787"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "10635",
                "label": "Funkeln2-bi",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2025/03/Funkeln2-bi-225x300.jpg",
                "width": "384",
                "height": "512"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "10636",
                "label": "Funkeln1_bi",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2025/03/Funkeln1_bi-300x225.jpg",
                "width": "512",
                "height": "384"
              }
            ],
            "gallery_layout": "grid"
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Aus Fabrikklang wird Radio.",
            "body": "Worum es geht\n\nGemeinsam begeben sich Kinder, Eltern und Künstlerinnen auf Klangsuche in den Hallen der ehemaligen Fabrik. Sie fangen Geräusche ein, mischen sie mit eigenen Sounds, Stimmen und Fantasie und entwickeln daraus kurze Radioformen, Hörspiele und Klangbilder.\n\nDas Projekt übersetzt Industriekultur nicht in eine Führung, sondern in eigenes Tun. Was sonst als Rest, Geräusch oder Nebenprodukt wahrgenommen wird, wird Material: Schritte, Hall, Maschinenassoziationen, Erzählungen und Bilder aus Schöneweide.",
            "anchor": "worum-es-geht",
            "links": []
          },
          {
            "type": "facts",
            "kicker": "Fakten",
            "title": "Eckdaten",
            "body": "",
            "anchor": "worum-es-geht-fakten",
            "facts": [
              {
                "value": "Kinder &amp; Eltern",
                "label": "Gemeinsame Workshops, in denen Generationen über Klang und Fantasie zusammenarbeiten."
              },
              {
                "value": "Klang &amp; Bewegung",
                "label": "Geräusche, Körper, Stimmen und Sampling werden als künstlerische Werkzeuge genutzt."
              },
              {
                "value": "Digitales Radio",
                "label": "Die Ergebnisse werden als Installation im Industriesalon hör- und sichtbar."
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Erzählen, hören, sampeln, bauen.",
            "body": "Konzept\n\nDie Reihe verbindet Industriekultur mit künstlerischer Forschung. Ausgangspunkt sind nicht nur historische Fakten, sondern sinnliche Spuren: Klang, Raum, Stimme, Erinnerung und die Frage, wie Kinder ihren eigenen Zugang zu Industriegeschichte formulieren.",
            "anchor": "konzept",
            "links": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Künstlerische Begleitung und Orte.",
            "body": "Mitwirken\n\nDie Workshops wurden künstlerisch von Irene Accardo und Ariel William Orah begleitet. Der Industriesalon stellte den historischen Resonanzraum bereit; Novilla ergänzte den Arbeitskontext für Radioformate, Sampling und Audiobearbeitung.",
            "anchor": "mitwirken",
            "links": []
          },
          {
            "type": "material",
            "kicker": "",
            "title": "Radio, Plakat, Projektzeichen.",
            "body": "Material\n\nDie Materialien zeigen das Projekt als Prozess und Ergebnis: Workshopbilder, das grafische Projektmotiv, das Plakat und das digitale Radio als Installation.\n\nDas digitale Radio erzählt Geschichten, serviert Wellensalat und zeigt Bilder aus der Workshop-Reihe. Es macht die Ergebnisse nicht nur als Rückblick sichtbar, sondern als Objekt im Raum erfahrbar.",
            "anchor": "material",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "11133",
                "label": "Bild4",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/01/Bild4-300x267.jpg",
                "width": "787",
                "height": "700"
              }
            ],
            "links": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Von der Workshop-Reihe zur Installation.",
            "body": "Rückblick",
            "anchor": "rueckblick",
            "links": []
          },
          {
            "type": "schluss",
            "kicker": "",
            "title": "Fragen zur Dokumentation oder zu Anschlussformaten.",
            "body": "Kontakt\n\nFür Rückfragen zur Projektarchivierung, zur Installation oder zu möglichen Anschlussworkshops läuft der Einstieg über den allgemeinen Kontakt des Industriesalon.",
            "anchor": "kontakt",
            "links": []
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 24828,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "9567"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [
          "event.festival"
        ]
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "4eecff3cd446fd985288db40950340ac8b752e7911b5e66942bda57215ea0185",
        "post_content": "db3c2532ec7bb518e4ec331749bda73d84d8d8767582cd168efa8fee6a2f646b",
        "post_title": "0d59420c9c7da7ea264fc5874344985f5535441019646e3916d9935a8b5a3b5e",
        "post_excerpt": "207eb6c0b8e1896d9e72c288fe72ebcab600802369dc8d9bff7f19aca82b69be",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "d15169764c754236018e3a9eea256a600ca0b93d92ae5d5db77de0cea2192c05",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a5f2808cadea130dce760ba4cc695ddd16ac5cff27312f8755a45beb06fce809",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "8588b1d62402189cb982e048d87037be576f55f11b1ffc51a904a5bdb7a8cce5"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [
          "d263c73b8546b331696654a29212c5b2df640c538b9be8a0d107dbfed36b2237"
        ],
        "_iss_content_json": [
          "4f7bb04e74144c21593205948106f5379761262af87829e30ae9f3860eea5b62"
        ],
        "_iss_editorial_enabled_veranstaltung": [],
        "_iss_editorial_veranstaltung_skin": []
      },
      "identity": {
        "type": "veranstaltung",
        "slug": "tag-des-offenen-denkmals"
      },
      "format": "veranstaltung",
      "document": {
        "schema_version": 1,
        "skin": "typografisch",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "Zeitzeugen aus der Elektropolis\nSonderprogramm zum Tag des offenen Denkmals am Sonntag, 8.9.2024\nBei einer Führung durch das historische Industriegebiet werden Videos von ehemaligen Werktätigen an den authentischen Arbeitsorten gezeigt.\n\nStart der 1. Führung: 11Uhr\nStart der 2. Führung: 13Uhr\n\nDie Führung ist kostenlos. Eine Anmeldung bis zum 5.9.24 ist erforderlich an: info@industriesalon.de – Stichwort. Tag des offenen Denkmals\n\nTreffpunkt: Industriesalon Schöneweide, Reinbeckstraße 10, 12459 Berlin\nTelefonische Informationen: 030 - 53 00 70 42 (Bürozeiten: Dienstag bis Donnerstag 10 – 16 Uhr, Freitag 10 – 14 Uhr)",
            "media_refs": [],
            "dynamic_refs": []
          }
        ],
        "deleted_sections": [],
        "entity_key": "event.festival"
      },
      "enabled": true
    },
    {
      "id": 24996,
      "new": false,
      "post": {
        "post_content": "Zwischen Röhren, Trafos und Werksgeschichten bewahrt der Industriesalon auch Spuren der Werbegestaltung aus der Arbeitswelt des TRO. Dieser Beitrag erzählt von Wolfgang Wehner, der in den 1960er Jahren in der Werbeabteilung des Transformatorenwerks Oberschöneweide arbeitete und dort unter anderem die Werbung für den Rasenmäher „Trolli“ betreute.\r\n\r\nAusgangspunkt ist ein Brief Wehners an den Industriesalon, ergänzt um Bildmaterial zum Produkt und seiner Gestaltung. So wird sichtbar, dass Industriegeschichte in Schöneweide nicht nur aus Maschinen, sondern auch aus Entwurf, Konsum und persönlicher Erinnerung besteht."
      },
      "meta": {
        "_thumbnail_id": [
          "6641"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "a6e527b3ecb4c64f4e3827e92356b780ca02aedb1a0151c7c56abb44fb016ce0",
        "post_content": "265d8a38fff686d21e251cd681e312e36178c0bc070e88dd208dc2a710522528",
        "post_title": "109f19e48b29e5842d1faec543d1124efac6df1f72f0336e0fe1be2962fb5fd4",
        "post_excerpt": "bfac9d8782f803d60730fd128c4c09adcb00462354d4bbd0c50eed9451a6783a",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "cf68f39367b2a00a0c7a360b877ef6c280e644249e2e3ec8d64399a8e12221e5",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "cfafe91804edaaf2ab18fced8ed589ba30b0201bb9698a816bc013635543d3f5",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "8cde11c9265e03bc03c1a785c27d897b674ced5da3cb3335faa32f401b2e58a4"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "identity": {
        "type": "archivbeitrag",
        "slug": "die-trolli-werbung"
      }
    },
    {
      "id": 25525,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [
          "1"
        ],
        "iss_archive_browser_lock_source": [
          "1"
        ],
        "iss_archive_browser_show_source_cards": [
          ""
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "f31c23a50881c6630b3f41f81cb76f5748e8620d2bc939ed5a499bba126e74b3",
        "post_content": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_title": "ed7393837fceb4e063f2e3808e267713c918474e6a8834a9267c957dd05893e5",
        "post_excerpt": "239652e6b405f127be4e06ef1e2875f73d1cd4e0d11e4739297e1f77e3432219",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "f2b22003846c5151c3b6b6ce693002585b657df8043e042ad633f0fb7657e807",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_source": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_show_source_cards": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "menschen-im-wf"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 25527,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [
          ""
        ],
        "iss_archive_browser_lock_source": [
          "1"
        ],
        "iss_archive_browser_show_source_cards": [
          ""
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "f31c23a50881c6630b3f41f81cb76f5748e8620d2bc939ed5a499bba126e74b3",
        "post_content": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_title": "136c62343828ccddc522543cec50f6141c7390e892a90ea5b66c2d43dff681db",
        "post_excerpt": "bbc1aae5d1ba0488108ab91e4e76ea22dc41a6d6047deeeef1e2b19be54c1f0a",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "697ebdd861919323f67bc3d6cfee53a9eec11ccd6dbe9b5bdb7fe5b0ceeff4ae",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "iss_archive_browser_lock_source": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_show_source_cards": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "roehren-und-halbleiter"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 25529,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [
          ""
        ],
        "iss_archive_browser_lock_source": [
          "1"
        ],
        "iss_archive_browser_show_source_cards": [
          ""
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "f31c23a50881c6630b3f41f81cb76f5748e8620d2bc939ed5a499bba126e74b3",
        "post_content": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_title": "6dfe40810ddde712a921712d1f05ce20a1d7309efc26910485775e4a53cb6670",
        "post_excerpt": "892c54eb8563c2b9947be583e87e06460ed7b379cf71c3965f83c3604e7fd538",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "d7e02524cf8ce95f87d2189f17e96685f6c8ade1a665842d97f48d5453b1872c",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "iss_archive_browser_lock_source": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_show_source_cards": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "anlagen-automaten-arbeitsplaetze"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 25531,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [
          "1"
        ],
        "iss_archive_browser_lock_source": [
          "1"
        ],
        "iss_archive_browser_show_source_cards": [
          ""
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "87c3488248f56a9bc35e968b81464591ad5297505777a1bb4bf9f55c9d25ae8a",
        "post_content": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_title": "72d10af6abd03dada1eedc2850a69d4e24933b0238e15d3f65f6c23f90594cb5",
        "post_excerpt": "7c795a0f6fce73f4cbbd6568d9c163f4962ffd41d464de6f5251eb31fd5fbda3",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "21968814547e3cbb584b8e313b9ae77af604e6b115bcf789c83e088fc2af1104",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_source": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_show_source_cards": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "telekommunikation-sende-und-fernsehtechnik"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 25533,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [
          "1"
        ],
        "iss_archive_browser_lock_source": [
          "1"
        ],
        "iss_archive_browser_show_source_cards": [
          ""
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "87c3488248f56a9bc35e968b81464591ad5297505777a1bb4bf9f55c9d25ae8a",
        "post_content": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_title": "6d73d6ee7d18abcc5b2632e20f6cc0aa3f646f9c9ae296cce49735f1a2fcb109",
        "post_excerpt": "08b4558d51b88db178f6ed9f3460db04d1dc10373247d11eb59005252b768e0b",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "ab17126a316d8002e4838e49d3d9d4252196f34f97e1370ddca1facd6bcba912",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_source": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_show_source_cards": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "diverses-gebaeude-schaltbilder"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 25535,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          ""
        ],
        "iss_archive_browser_lock_field": [
          "1"
        ],
        "iss_archive_browser_lock_source": [
          "1"
        ],
        "iss_archive_browser_show_source_cards": [
          ""
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "87c3488248f56a9bc35e968b81464591ad5297505777a1bb4bf9f55c9d25ae8a",
        "post_content": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "post_title": "c24e46c0ff211c22689105aeacf07098103223d0d1d5a7a77915acb1ffe521f8",
        "post_excerpt": "668d28d194b9fd85d63ab9261b6f1a7057683345fdc60ab6b00f3ffd1a25bc91",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "d3a328dde34751819c9dcfa33909093325994a164e7fda5a9dd41c34f3bf338b",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_source": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_show_source_cards": [
          "12ae32cb1ec02d01eda3581b127c1fee3b0dc53572ed6baf239721a03d82e126"
        ],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "geraete-einschuebe-bauteile"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "standard",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 25657,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [
          "25664"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "dd0dbd5b8f85e4c1d754f5ea999bf59e39fc84e0dbc72126f90692b16ca5020a",
        "post_content": "021c6c9880e1e41013198a757438593ac60c42d7d80d95b29ae3308ed6c8d1ac",
        "post_title": "852eb14d424ff7cff4e4c1b398e9f51c589d8d2329a51b2b97edb5dc879ea351",
        "post_excerpt": "27992ed331ef92ecdf32484d2bb9ee02339999113f16e6770b86fecdf11b3df7",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "d4ad430b91bb837d34acfb54e75f534a42d6e74de730a2fc4289f43192a781c1",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "e629fa6598d732768f7c726b4b621285f9c3b85303900aa912017db7617d8bdb",
        "post_type": "1e09062a43327744b3f2a8e8496d28968dc502138abb68128da97f80139d3cd5",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "c55dbe1b31a8895e7647decd326e1ab774e5444f73f201094bfd65f78813c020"
        ],
        "_wp_page_template": [
          "1080a60800d9e5bddc69f51489c636a776bacaadb5368f46db4aa0947c67e3ba"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_projekt": [
          "cfb4f875bf723e74f74e092dcbfc1fc1f52078464df45bc34f88093455371781"
        ],
        "_iss_editorial_enabled_projekt": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_projekt_skin": []
      },
      "identity": {
        "type": "projekt",
        "slug": "futura-biennale-2027"
      },
      "format": "projekt",
      "document": {
        "schema_version": 1,
        "skin": "dossier",
        "variant": "standard",
        "features": {
          "rail": {
            "enabled": true
          }
        },
        "sections": [
          {
            "type": "fliesstext",
            "kicker": "",
            "title": "",
            "body": "Stadt bauen beginnt mit Gesprächen, die sonst nicht stattfinden. Die Futura Biennale 2027 verbindet Stadtentwicklung und soziale Innovation in einem kompakten, öffentlich sichtbaren Verfahren für Schöneweide.",
            "links": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Stadt bauen beginnt mit Gesprächen, die sonst nicht stattfinden.",
            "body": "Worum es geht\n\nDie Futura Biennale 2027 verbindet Stadtentwicklung und soziale Innovation in einem kompakten, öffentlich sichtbaren Verfahren. Sie überträgt die Energie eines interdisziplinären Sprints auf den Maßstab eines Stadtteils und koppelt die Ergebnisse an eine Messe für techno-soziale Innovation, auf der Ideen, Räume und Kapital zueinanderfinden.\n\nWir hatten den Mut, gemeinsam zu entwerfen, zu erproben und durchzuhalten. Heute zeigt sich, was 2025 mit einer Skizze auf der ersten Futura begann und was 2026 mit einem Vorbereitungstreffen weitergedacht wurde.\n\nDu steigst am S-Bahnhof aus, der Wind kommt von der Spree. Auf dem Weg zum Wasser läufst Du durch ein Hybrid-Quartier, in dem urbanes Leben und Arbeiten neu verbunden sind. Du passierst den Klima-Sensor-Garten, in dem Luft, Lärm und Wärme des Quartiers in Lichtsäulen sichtbar werden. Daneben der Bauhof für zirkuläres Bauen, ein paar Schritte weiter das Bio-Fashion-Atelier, in dessen Schaufenstern Mycelium-Leder neben 3D-gedruckten Garderoben hängt.\n\nWeiter am Familien-Coworking vorbei und am Handwerker-Bildungs-Hof, in dem Schmiede, 3D-Drucker und Lehrlinge nebeneinander arbeiten. Auf der Bewegungs-Wiese Schatteninseln, Schwammstadt-Boden und an der Kante zur Spree die Spreeterrassen zum Chillen.\n\nDu weißt: nichts davon kam von allein. Hinter jedem dieser Orte stehen Zielkonflikte und radikale Kompromisse zwischen Wachstum und Erhalt, zwischen Privatem und Öffentlichem, zwischen schnell und sorgfältig. Am Ufer angekommen drehst Du Dich um und siehst Dein Ziel: den Salon der Übermorgen, vormals Industriesalon Schöneweide, heute beides, Speicher der Geschichte und Labor der Übermorgen.\n\nNach der Durchführung liegen konkrete Projekt- und Nutzungsideen für benannte Flächen, Infrastrukturen und öffentliche Räume in Schöneweide vor. Es gibt belastbare Kooperationen zwischen Eigentümern, Wissenschaft, Zivilgesellschaft, Verwaltung und Finanzierungspartnern.\n\nSichtbare Prototypen und eine geschärfte Erzählung tragen den Standort in die Öffentlichkeit. Und es gibt einen verabredeten Folgeprozess mit nächsten Schritten, Zuständigkeiten und Anschlussoptionen: Die Patenschaften laufen über mindestens zwölf Monate, ein jährliches Follow-up bilanziert den Stand, und die Futura 2029 baut auf den Ergebnissen auf.",
            "anchor": "worum-es-geht",
            "links": []
          },
          {
            "type": "facts",
            "kicker": "Fakten",
            "title": "Eckdaten",
            "body": "",
            "anchor": "worum-es-geht-fakten",
            "facts": [
              {
                "value": "12 Tage",
                "label": "Lab und Messe in vier Phasen, von der Standortbegehung bis zur Preisvergabe."
              },
              {
                "value": "2 Spuren",
                "label": "Wohnen und Arbeiten neu gedacht sowie soziale Infrastruktur im Quartier."
              },
              {
                "value": "3+ Flächen",
                "label": "Eigentümer mit Flächenzusage sind Bedingung für echte Umsetzungspreise."
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Zwölf Tage. Zwei Spuren. Ein Stadtteil.",
            "body": "Konzept\n\nVier Phasen, die nicht beliebig sind: vom gemeinsamen Problemverständnis über den Lab-Sprint und die Tragfähigkeitsprüfung bis zur Messe für techno-soziale Innovation. Schöneweide ist dabei nicht Kulisse, sondern Gegenstand und Umsetzungsort.\n\nDie Futura überträgt die Energie eines interdisziplinären Sprints auf den Maßstab eines Stadtteils und koppelt die Ergebnisse an eine Messe für soziale Innovation, auf der Ideen, Räume und Kapital zueinanderfinden. Sie öffnet einen Korridor, in dem spätere Entscheidungen über Flächen, Nutzungen und Partnerschaften schneller, offener und besser informiert getroffen werden können.\n\nWas hier entworfen wird, soll hier erprobt, verbunden und weitergeführt werden. Die Futura ist Startpunkt eines strukturierten Folgeprozesses, nicht dessen Ersatz.\n\nDie bisher diskutierte Variante von zehn Lab-Tagen wird verworfen. Sie ist für Hackathon-Energie zu lang und für Stadtentwicklung zu kurz. Die neue Dramaturgie nimmt beide Spannungen ernst.\n\nDie Futura 2027 arbeitet mit zwei parallelen Hauptspuren, ergänzt um einen Jugend-Strang, der in beide Spuren eingreift. Querschnittsthemen wie digitale Souveränität, KI-gestützte Stadtplanung, Mobilität und Kreativwirtschaft fließen ein, werden aber nicht als eigenständige Tracks geführt. Das schärft den Fokus.\n\nHybride Nachbarschaften auf bestehenden Gewerbeflächen. Wohnen wird nicht als isoliertes Problem behandelt, sondern flächenbezogen und Schöneweide-spezifisch verhandelt: Wie entstehen neue Mischnutzungen, die Gewerbeerhalt und Wohnraumbedarf verbinden?\n\nBildung, Versorgung, Begegnung. Wie trägt ein wachsendes Quartier seine soziale Grundausstattung? Welche Rolle spielen Genossenschaften, Vereine, Initiativen? Welche Räume fehlen, welche sind ungenutzt?\n\nJedes Team hat mindestens zwei Teilnehmende unter 25 Jahren. Ein Teil der Aufgabenstellungen wird von Jugendlichen selbst formuliert und von ihnen kuratiert, nicht nur moderiert. Schulen aus Schöneweide und die HTW Berlin werden als Partner gewonnen. Ein separater Jugend-Track wird abgelehnt, weil er erfahrungsgemäß zum Nebentisch wird.\n\nDie Bewertung folgt zwei Kreisen, die gleichgewichtig nebeneinander stehen. Die Fachjury beurteilt Tragfähigkeit, Umsetzbarkeit, Innovationsgehalt und Passung zu Schöneweide, besetzt mit Wissenschaft, Planungspraxis, Verwaltung, Kreativwirtschaft und Zivilgesellschaft.\n\nDas Umsetzungsvotum kommt vom Messepublikum, von Eigentümern und Finanziers. Sie vergeben ein verbindliches Votum für das Projekt, dem sie tatsächlich Raum, Kapital oder Patenschaft zusagen würden. Nicht Applaus entscheidet, sondern Absicht.\n\nDie Futura vergibt keine Geldpreise. Die Preise sind Zugänge: zu Flächen, zu Patenschaften, zu Finanzierung und zu Sichtbarkeit. Reale Umsetzungsorte in Schöneweide, mindestens zwölf Monate Begleitung, konkrete Gesprächszugänge und dokumentarische Sichtbarkeit ersetzen die reine Preisgeste.",
            "anchor": "konzept",
            "links": []
          },
          {
            "type": "facts",
            "kicker": "Fakten",
            "title": "Eckdaten",
            "body": "",
            "anchor": "konzept-fakten",
            "facts": [
              {
                "value": "Phase 1",
                "label": "Framing Vorab werden die Aufgabenstellungen mit den Kernakteuren geschärft. Die Teams kommen in Schöneweide an, lernen den Standort durch kuratierte Begehungen kennen und treffen auf Eigentümer, Verwaltung und Zivilgesellschaft. Am Ende steht nicht der erste Entwurf, sondern das gemeinsame Problemverständnis."
              },
              {
                "value": "Phase 2",
                "label": "Lab-Sprint Der eigentliche Hackathon-Kern. Die Teams arbeiten in zwei thematisch unterschiedenen Spuren, die nebeneinander laufen und am Schlusstag in einer gemeinsamen Synthese konvergieren. Der Sprint ist geschlossen, intensiv, mit klaren Deliverables."
              },
              {
                "value": "Phase 3",
                "label": "Schärfung Die Entwürfe treffen auf Eigentümer, Fachleute, Verwaltung und Finanzierungspartner. Hier findet die erste Tragfähigkeitsprüfung statt. Diese Phase macht den Unterschied zwischen naivem Entwurf und anschlussfähiger Konzeption."
              },
              {
                "value": "Phase 4",
                "label": "Messe für techno-soziale Innovation Die öffentliche Phase. Ergebnisausstellung der Lab-Teams, Flächen-Präsentationen der Eigentümer, Panels, Science Slam, Souveränitäts-Labs, Jugend-Programm. Die Messe ist kein Ausstellungsparcours, sondern ein aktiver Marktplatz mit zweistufiger Bewertung und Umsetzungspreisen."
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Was bringst Du mit: Idee, Räume, Zeit, Geld, Wissen, Patenschaft?",
            "body": "Mitwirken\n\nDie Futura Biennale 2027 lebt vom Verbund. Drei bis fünf Schlüsselakteure entscheiden, ob sie stattfindet. Wer jetzt einsteigt, prägt das Format mit: Spuren, Aufgabenstellungen, Bewertungskreis, Folgeprozess. Es gibt keinen fertigen Plan, den man bestätigt. Es gibt einen Korridor, den wir zusammen öffnen.\n\n19. Mai 2026, 18 Uhr, Industriesalon Schöneweide, Reinbeckstraße 10, 12459 Berlin. Vor der Sommerpause möchten wir mit einer innovativen Konzeption gemeinsam durchstarten: drei Fragen, ein Abend, viele Stimmen.\n\nErst wenn diese Gespräche geführt sind, lohnt die weitere Verfeinerung des Konzepts. Wir suchen verbindliche Partner, keine Sympathisanten.\n\nNicht jede Form der Beteiligung muss eine der vier Schlüsselrollen besetzen. Vereine, Initiativen und Einrichtungen aus dem Quartier bringen eigene Vorschläge in den Wettbewerb der Vereine, Initiativen und Einrichtungen ein. Schulen aus Schöneweide kuratieren, nicht nur moderieren, den Jugend-Strang. Praktiker:innen, Wissenschaftler:innen und Kreative bewerben sich als Teams für den Lab-Sprint.\n\nAuch Berliner Zukunftsorte und bundesweite Transformationsareale sind eingeladen, ihre Ansätze auf der Messe zu zeigen. Die Futura bindet das Schöneweider Format bewusst in den größeren Stadtkontext ein.",
            "anchor": "mitwirken",
            "links": [
              {
                "label": "Kontakt aufnehmen",
                "url": "mailto:k.burmeister@industriesalon.de?subject=Futura%20Biennale%202027"
              }
            ]
          },
          {
            "type": "material",
            "kicker": "",
            "title": "Welcher Zukunftstyp bist Du?",
            "body": "Zukunftsquiz\n\n15 Fragen, 7 Typen, 3 Minuten. Das Zukunftsquiz der Futura ist mehr als ein Spiel: Es ist ein Spiegel der eigenen Haltung zu Umbruch, Risiko und neuen Möglichkeiten. Mit Profildiagramm im Radar-Chart, drei Deutungsachsen und Zukunfts-Dating für Typ-Kompatibilität. Mobile-optimiert, ohne Login, datensparsam.\n\nJeder Typ ist eine eigene Art, mit Umbruch umzugehen. Die meisten Menschen tragen mehrere in sich. Keine Wertung, kein Schubladen-Denken: Sieben Stimmen, von denen wir alle brauchen.\n\nDas Quiz arbeitet mit 15 Fragen, die jeweils vier Antwortoptionen anbieten. Jede Antwort fließt gewichtet in mehrere Typen ein. Nach der letzten Frage wird Dein Profil auf drei Achsen ausgewertet: Zeitorientierung, Handlungsmodus und Weltzugang. Reibung und Resonanz werden kompakt verpackt.\n\nDie Futura arbeitet mit einer These: Stadt bauen beginnt mit Gesprächen, die sonst nicht stattfinden. Damit Gespräche entstehen, braucht es einen Anlass. Das Zukunftsquiz ist genau das: ein leichter Einstieg in eine schwere Frage. Auf der Futura 2025 lief es auf Postern, QR-Codes und Smartphones; für die Futura 2027 ist es als Onboarding, Messe-Aktivierung und spielerischer Begleiter gesetzt.",
            "anchor": "material",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "25659",
                "label": "Drei Minuten genügen. Das Quiz läuft auf jedem Smartphone, ohne Anmeldung. Nach den 15 Fragen bekommst Du Deinen Hauptzukunftstyp, ein Radar-Profil über alle sieben Typen und ein Zukunfts-Dating mit dem Typ, der am besten zu Dir passt. Quiz öffnen",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/zukunftsquiz_qrcode-300x300.png",
                "width": "940",
                "height": "940"
              }
            ],
            "links": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "2025 hat etwas in Bewegung gebracht.",
            "body": "Rückblick 2025\n\nDie erste Futura war Versuch, Werkstatt und Auftakt zugleich: ein Format auf der Suche nach seiner Form. Was wir aus ihr lernen, prägt die Futura 2027: schärfer gefasst, mit klarerer Dramaturgie und einem Folgeprozess, der nicht beim Applaus endet.\n\nVon der Auftaktveranstaltung bis zum Finale spannten sich verschiedene Formate nebeneinander auf: Diskurs, Werkstatt, Spiel und Begegnung. Eine Auswahl der Stationen, auf die die Futura 2027 aufbaut.\n\nAus der ersten Futura ist mehr hervorgegangen als ein Veranstaltungs-Rückblick. Die Schöneweider Zukunftspreise haben einen ersten Träger gefunden: die Schöneweider KiezKarte, Idee Dirk Sarnoch / WERK 116, ausgezeichnet mit dem Jurypreis. Die Karte verbindet lokale Kaufkraft mit einem Bürgerfonds für Kultur, Open-Air-Kino und Nachbarschaft.\n\nGenau dieses Muster, von der Skizze zur tragfähigen Umsetzung mit konkreten Akteuren vor Ort, soll die Futura 2027 systematisch verstetigen. Die Träger der Schöneweider Zukunftspreise werden bis zur Futura 2029 begleitet.\n\nErstens: Energie braucht eine Dramaturgie. Die zehn Lab-Tage waren für Hackathon-Energie zu lang und für Stadtentwicklung zu kurz. Die Futura 2027 antwortet mit vier Phasen über zwölf Tage: Framing, Sprint, Schärfung, Messe.\n\nZweitens: Bewertung muss verbindlich werden. Applaus reicht nicht. Die zweistufige Bewertung aus Fachjury und Umsetzungsvotum sorgt dafür, dass Preise nur dort vergeben werden, wo Eigentümer, Finanziers oder Pat:innen sich konkret zusagen.\n\nDrittens: Jugend ist konstitutiv, nicht dekorativ. Ein separater Jugend-Track wird zum Nebentisch. In der Futura 2027 hat jedes Team mindestens zwei Teilnehmende unter 25 Jahren, und ein Teil der Aufgabenstellungen wird von Jugendlichen selbst formuliert.",
            "anchor": "rueckblick",
            "links": []
          },
          {
            "type": "schluss",
            "kicker": "",
            "title": "Reden und verbindlich werden.",
            "body": "Kontakt\n\nDie Futura entsteht im Gespräch. Schreiben Sie kurz, was Sie interessiert, was Sie einbringen können und mit wem wir sprechen sollten. Wir melden uns innerhalb weniger Tage mit Konzeptpapier und Terminvorschlag.\n\nEine Initiative des Industriesalon Schöneweide e.V., getragen von Vorstand, Mitgliederschaft und einem wachsenden Verbund aus Standortakteuren, Hochschulen und Zivilgesellschaft.\n\nKlaus BurmeisterIndustriesalon Schöneweide e.V. · Vorsitzk.burmeister@industriesalon.de\n\nIndustriesalon Schöneweide e.V.Reinbeckstraße 10, 12459 Berlininfo@industriesalon.de\n\nDer Industriesalon liegt im historischen Kabelwerk-Areal, mitten in der Wilhelminenhof. Die Adresse ist auch der Tagungsort der Futura: Halle, Hof und Außenraum bieten Platz für Lab und Messe.\n\nAuf Anfrage stellen wir das aktuelle Konzeptpapier, Logo-Varianten, Pressetext und Bildmaterial aus der Futura 2025 zur Verfügung. Bitte kurzes Anschreiben mit Medium und Verwendungszweck an k.burmeister@industriesalon.de.",
            "anchor": "kontakt",
            "links": [
              {
                "label": "Mail schreiben",
                "url": "mailto:k.burmeister@industriesalon.de?subject=Futura%20Biennale%202027"
              }
            ]
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 25720,
      "new": false,
      "post": {
        "post_excerpt": "Große Erfindungen, berühmte Physiker, Produkte für den Weltmarkt - die historischen Industriegebäude entlang der Wilhelminenhofstraße\r\nzeugen bis heute von der Innovationskraft der Berliner Elektropolis. \r\n\r\nDer Walk of Fame Schönweide nimmt den Standort mit einer künstlerischen Installation von Sehrohren in den Fokus.\r\n"
      },
      "meta": {
        "_thumbnail_id": [
          "25803"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "05e836467f751caba8ab758cb088cbc7937ec69ba334b6794e6a212ab33c8546",
        "post_content": "169be155adb4df7057dc4ca82eb5b1f2602eda4fcce4e1419072d589b4a7382e",
        "post_title": "8c46cc0a0fda2e777701568dd13e0de3720db2c11f7c0c62a59409c5945f785a",
        "post_excerpt": "fc1af31057aca5d64b40a2d63ab43b38cd05d08f38a8c143fcc776cc419fcdfc",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "2788e9781e58ef1d740537d3e540538d547609ab140762759bd0e9a3c1a51105",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "4a44dc15364204a80fe80e9039455cc1608281820fe2b24f1e5233ade6af1dd5",
        "post_type": "1e09062a43327744b3f2a8e8496d28968dc502138abb68128da97f80139d3cd5",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "2e7b33384f350240082d2fd73219cd840706389543e198474bfdcd94a78f264c"
        ],
        "_wp_page_template": [
          "1080a60800d9e5bddc69f51489c636a776bacaadb5368f46db4aa0947c67e3ba"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_projekt": [
          "f74d7beb211b439356963a2eef42e0e76cb0956afbb2c2b40b8a9c3808b35619"
        ],
        "_iss_editorial_enabled_projekt": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_projekt_skin": []
      },
      "identity": {
        "type": "projekt",
        "slug": "walk-of-fame-schoeneweide"
      },
      "format": "projekt",
      "document": {
        "schema_version": 1,
        "skin": "dossier",
        "variant": "standard",
        "features": {
          "rail": {
            "enabled": false,
            "placement": "left",
            "mode": "contextual",
            "treatment": "quiet"
          }
        },
        "sections": [
          {
            "type": "material",
            "kicker": "Flyer",
            "title": "Aktueller Flyer",
            "body": "",
            "anchor": "material",
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26695",
                "label": "16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/event-drop-storage/accepted/16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide-pdf-300x169.jpg",
                "width": "0",
                "height": "0"
              }
            ],
            "links": []
          },
          {
            "type": "kapitel",
            "kicker": "Mitwirken",
            "title": "Patenschaften halten den Walk of Fame im Quartier.",
            "body": "<p><br></p><p>Zum <strong>„Fame“ </strong>von Schöneweide gehören auch die Unternehmen, Headquarter und Einrichtungen, die heute am Standort arbeiten. Sie sollen als Nachbarn sichtbar werden und zugleich helfen, die Sehrohre vor Diebstahl und Zerstörung zu schützen.</p>",
            "anchor": "mitwirken",
            "links": []
          },
          {
            "type": "upload_intake",
            "kicker": "Hochladen",
            "title": "",
            "body": "",
            "links": []
          },
          {
            "type": "galerie",
            "kicker": "Bilder",
            "title": "Ausgewählte Bilder",
            "body": "Die Basisfinanzierung ist laut Flyer über die Projektförderung des Vereins durch die Senatsverwaltung für Wirtschaft, Energie und Betriebe sowie über bezirksübergreifende City-Tax-Mittel gedeckt. Die Standorte entlang der Wilhelminenhofstraße sind genehmigt; die nächste Aufgabe ist die Abstimmung mit Partnern und Paten im Quartier.\n\n",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26182",
                "label": "media",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/media-300x225.webp",
                "width": "1448",
                "height": "1086"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26179",
                "label": "schoeneweide-1918",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/schoeneweide-1918-300x225.webp",
                "width": "1448",
                "height": "1086"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26170",
                "label": "1_x-weltfestspiele-der-jugend-und-studenten-in-ostberlin-1973-bild-16-b-gruppe-von-tradition",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/1_x-weltfestspiele-der-jugend-und-studenten-in-ostberlin-1973-bild-16-b-gruppe-von-tradition-300x200.webp",
                "width": "1536",
                "height": "1024"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26171",
                "label": "1_wandzeitung-betriebschronik-vlv-nr-3-foto-1987-65270",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/1_wandzeitung-betriebschronik-vlv-nr-3-foto-1987-65270-300x200.webp",
                "width": "1536",
                "height": "1024"
              }
            ],
            "gallery_layout": "sequence"
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 25729,
      "new": false,
      "post": {},
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [
          "event.general"
        ]
      },
      "expected_fields": {
        "post_author": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_date": "7aa432bd31a0c61e721d12875a829862d217014a5b7c9393c944ccbb08c0d606",
        "post_content": "300865abda4ed9024a59f707ebda67da9820583dc10a1ad005308feb5d39cb0b",
        "post_title": "4ab630d6285c5b8e79f9cc7a6b322dd69378885b1f203845f3c529f91fd0bf2a",
        "post_excerpt": "38290cb0a13e2bbc8efde3d9d3e817ecf7adadb395db23ddabaa02df5ebe07d4",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "ae1fa19dc83dbfa05bf4aef97b0402d87e24b56a57427c74ba54f8feeb6865a3",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a5f2808cadea130dce760ba4cc695ddd16ac5cff27312f8755a45beb06fce809",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [
          "dd453b113a44b03e8037065878995e80114e3bfc7c18b8d3cfe8b952487e739e"
        ],
        "_iss_content_json": [
          "38bd010a4b727b99f1b807248ff8c884e2b266e60a76c29a0347b36d83243b32"
        ],
        "_iss_editorial_enabled_veranstaltung": [],
        "_iss_editorial_veranstaltung_skin": []
      },
      "identity": {
        "type": "veranstaltung",
        "slug": "salongespraech-vom-umbruch-zum-aufbruch-hidden-champions-aus-schoeneweide"
      },
      "format": "veranstaltung",
      "document": {
        "schema_version": 1,
        "skin": "typografisch",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "intro",
            "kicker": "",
            "title": "",
            "body": "Das Schöneweider Salongespräch stellte den Übergang des Werks für Fernsehelektronik nach 1990 als Geschichte von Wissenstransfer, Ausgründung und neuer industrieller Praxis vor.\n\nDr. Peter Strunk eröffnete mit einer Einführung zum Werk für Fernsehelektronik in Berlin-Schöneweide und zum Verschwinden der Industrie in Berlin nach 1989. Anschließend diskutierten Vertreterinnen und Vertreter erfolgreicher ehemaliger WF-Mitarbeiter und Ausgründungen über Erfahrungen aus der DDR-Industrie, Gründungspraxis und Standortkompetenz.\n\nFür den Industriesalon ist diese Veranstaltung ein wichtiger Übergangspunkt zwischen WF-Geschichte, Transformationszeit und den heutigen Technologie- und Zukunftsorten in Schöneweide.",
            "media_refs": [],
            "dynamic_refs": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Programm",
            "body": "Quelle: Bezirksamt Treptow-Köpenick, Pressemitteilung vom 05.04.2019.",
            "media_refs": [],
            "dynamic_refs": []
          }
        ],
        "deleted_sections": [],
        "entity_key": "event.general"
      },
      "enabled": true
    },
    {
      "id": 25808,
      "new": false,
      "post": {
        "post_content": "<!-- wp:group {\"className\":\"iss-event-program\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-event-program\">\n\t<!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker--compact iss-event-program__kicker\"} -->\n\t<p class=\"iss-kicker iss-kicker--compact iss-event-program__kicker\">Programm</p>\n\t<!-- /wp:paragraph -->\n\n\t<!-- wp:heading {\"level\":2,\"className\":\"iss-event-program__title\"} -->\n\t<h2 class=\"wp-block-heading iss-event-program__title\">Live-Musik zwischen 15 und 22 Uhr</h2>\n\t<!-- /wp:heading -->\n\n\t<!-- wp:paragraph {\"className\":\"iss-event-program__intro\"} -->\n\t<p class=\"iss-event-program__intro\">Zum Sommeranfang spielen sieben Acts im Industriesalon. Die Reihenfolge steht im Flyer; einzelne Set-Zeiten werden separat vor Ort bekanntgegeben.</p>\n\t<!-- /wp:paragraph -->\n\n\t<!-- wp:table {\"className\":\"iss-event-program__table\"} -->\n\t<figure class=\"wp-block-table iss-event-program__table\"><table><tbody><tr><td>Live</td><td>KWO-Klangwerk Oberspree</td><td>Bigband mit Power</td></tr><tr><td>Live</td><td>The Velvet Echoes</td><td>Warmer Harmoniegesang</td></tr><tr><td>Live</td><td>Jakab</td><td>Lieder mit Geschichten</td></tr><tr><td>Live</td><td>Crimson Sunday</td><td>Ultimativer Retro-Rock</td></tr><tr><td>Live</td><td>Izzy Diaz</td><td>Tanzbarer Folk aus Spanien</td></tr><tr><td>Live</td><td>Franziskas Erben</td><td>Punk-Pop mit Tiefgang</td></tr><tr><td>Live</td><td>Dances with Dogs</td><td>Rock voller Überraschungen</td></tr></tbody></table></figure>\n\t<!-- /wp:table -->\n</div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-event-fest-info\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-event-fest-info\">\n\t<!-- wp:group {\"className\":\"iss-event-fest-info__item\",\"layout\":{\"type\":\"default\"}} -->\n\t<div class=\"wp-block-group iss-event-fest-info__item\">\n\t\t<!-- wp:heading {\"level\":2} -->\n\t\t<h2 class=\"wp-block-heading\">Vor Ort</h2>\n\t\t<!-- /wp:heading -->\n\n\t\t<!-- wp:paragraph -->\n\t\t<p>Live-Musik, kühle Getränke und offene Hofatmosphäre im Industriesalon. Kommen und Gehen ist jederzeit möglich.</p>\n\t\t<!-- /wp:paragraph -->\n\t</div>\n\t<!-- /wp:group -->\n\n\t<!-- wp:group {\"className\":\"iss-event-fest-info__item\",\"layout\":{\"type\":\"default\"}} -->\n\t<div class=\"wp-block-group iss-event-fest-info__item\">\n\t\t<!-- wp:heading {\"level\":2} -->\n\t\t<h2 class=\"wp-block-heading\">Ort</h2>\n\t\t<!-- /wp:heading -->\n\n\t\t<!-- wp:industriesalon/field {\"key\":\"address.full\",\"tagName\":\"p\",\"linkMode\":\"none\"} /-->\n\t</div>\n\t<!-- /wp:group -->\n\n\t<!-- wp:group {\"className\":\"iss-event-fest-info__item\",\"layout\":{\"type\":\"default\"}} -->\n\t<div class=\"wp-block-group iss-event-fest-info__item\">\n\t\t<!-- wp:heading {\"level\":2} -->\n\t\t<h2 class=\"wp-block-heading\">Eintritt</h2>\n\t\t<!-- /wp:heading -->\n\n\t\t<!-- wp:paragraph -->\n\t\t<p>Der Eintritt ist frei. Eine Anmeldung ist nicht erforderlich.</p>\n\t\t<!-- /wp:paragraph -->\n\t</div>\n\t<!-- /wp:group -->\n</div>\n<!-- /wp:group -->"
      },
      "meta": {
        "_thumbnail_id": [
          "25807"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [
          "event.festival"
        ]
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "937e0c688e3603f2fcd93f581b7407b9203a7b6e34e075add9029188c28294f2",
        "post_content": "83c1e9b2b5dbdd4d3c664287f78f39778765a364446b1225f0fed5312b5bcc5f",
        "post_title": "046916ad8595e9c0640a813df7ce4531ccad59588ba12a8fb39fcb49a527a128",
        "post_excerpt": "f17255cbaade4034aefa16cceb3dbd3fef3226d5e6f8878617609f307456278a",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "10f7f181c5823990ab677b8548a2e8b1a85d51ce1315438401770954e930caf4",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a5f2808cadea130dce760ba4cc695ddd16ac5cff27312f8755a45beb06fce809",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "70d5c96ffa789537a5cb3eb1a6929d83d96692b7bd63eb933c7c164d77b805cb"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [
          "d263c73b8546b331696654a29212c5b2df640c538b9be8a0d107dbfed36b2237"
        ],
        "_iss_content_json": [
          "2ab7e0e13eb1646785515b7affe6fdb205180fd43c57d8b2ff2b48db1d4fe348"
        ],
        "_iss_editorial_enabled_veranstaltung": [],
        "_iss_editorial_veranstaltung_skin": []
      },
      "identity": {
        "type": "veranstaltung",
        "slug": "fete-de-la-musique-berlin-2026"
      },
      "format": "veranstaltung",
      "document": {
        "schema_version": 1,
        "skin": "typografisch",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Live-Musik zwischen 15 und 22 Uhr",
            "body": "Zum Sommeranfang spielen sieben Acts im Industriesalon. Die Reihenfolge steht im Flyer; einzelne Set-Zeiten werden separat vor Ort bekanntgegeben.\n\nLiveKWO-Klangwerk OberspreeBigband mit PowerLiveThe Velvet EchoesWarmer HarmoniegesangLiveJakabLieder mit GeschichtenLiveCrimson SundayUltimativer Retro-RockLiveIzzy DiazTanzbarer Folk aus SpanienLiveFranziskas ErbenPunk-Pop mit TiefgangLiveDances with DogsRock voller Überraschungen",
            "media_refs": [],
            "dynamic_refs": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Vor Ort",
            "body": "<p>Live-Musik, kühle Getränke und offene Hofatmosphäre im Industriesalon. Kommen und Gehen ist jederzeit möglich.</p><p><br></p>",
            "media_refs": [],
            "dynamic_refs": []
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Ort",
            "body": "",
            "media_refs": [],
            "dynamic_refs": [
              {
                "kind": "control_field",
                "source": "industriesalon-steuerung",
                "key": "address.full",
                "label": "Volle Adresse",
                "tagName": "p",
                "linkMode": "none"
              }
            ]
          },
          {
            "type": "kapitel",
            "kicker": "",
            "title": "Eintritt",
            "body": "<p>Der Eintritt ist frei. Eine Anmeldung ist nicht erforderlich.</p>",
            "media_refs": [],
            "dynamic_refs": []
          },
          {
            "type": "upload_intake",
            "kicker": "Hochladin",
            "title": "",
            "body": ""
          }
        ],
        "deleted_sections": [],
        "entity_key": "event.festival"
      },
      "enabled": true
    },
    {
      "id": 26287,
      "new": false,
      "post": {
        "post_excerpt": "Sie hielten den größten Elektronikbetrieb Ost-Berlins am Laufen – mit ruhiger Hand, enormer Präzision und unermüdlichem Einsatz"
      },
      "meta": {
        "_thumbnail_id": [
          "26365"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "1"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "b0b5b7d470a3b21130cc98eee71b1e166116bd21f9fa532fe2aea02b947c1b3b",
        "post_content": "2e7e0cb718d2cbff8a65850729e1821e0ff825b20af6ccefbb09c12c037cc420",
        "post_title": "5506d0505e4905d4931a6d8bcbcfad114ec5760dcc907a97bdae9d91bc419dbb",
        "post_excerpt": "816e420c51eb0c5a66311738399255fa7d9e13e8afc71575aa379aa7d62161eb",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "d13541900fa29fb73c076c28be0945bfaba9a3f4b8118b33af1be687ce3fdabc",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "f9cfdce0cd794d75ac371a7f274e03c69b42cb3f5336d6cfa06100472cb81d3b"
        ],
        "_wp_page_template": [
          "1080a60800d9e5bddc69f51489c636a776bacaadb5368f46db4aa0947c67e3ba"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "frauen-in-werk"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "quellenbuehne",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "leitfrage",
            "kicker": "",
            "title": "Wo die Zeit im Takt der Röhren schlug",
            "body": "Sie hielten den größten Elektronikbetrieb Ost-Berlins am Laufen – mit ruhiger Hand, enormer Präzision und unermüdlichem Einsatz. Die Frauen im Werk für Fernmeldewesen (WF).In staubfreien Hallen montierten sie winzige Gitter, schufen das Herzstück der Röhrenfertigung und balancierten jeden Tag zwischen sozialistischer Brigade, Karriere und dem ganz normalen Alltagswahnsinn. Sie standen im Scheinwerferlicht der DDR-Medien und feierten Frauentage als Rituale der Anerkennung.Die alten Werkhallen an der Spree schweigen heute. Doch diese Ausstellung holt ihre Gesichter, ihre Geschichten und ihr Lachen zurück ins Licht."
          },
          {
            "type": "zitat",
            "kicker": "Arbeit",
            "title": "Präzision war keine Nebenarbeit",
            "body": "In der Presstellerfertigung wurden feine Drahtstifte in Glassockel eingeschmolzen. Die Arbeit galt als Frauenarbeit, verlangte aber Konzentration, Materialwissen und ein genaues Gefühl für Temperatur und Tempo.",
            "quote": "„Von der Kollegin an der Presstellermaschine hängt es ab.“\n\n",
            "attribution": "",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26366",
                "label": "ALB_003-3",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/ALB_003-3-300x261.webp",
                "width": "1344",
                "height": "1170"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-left"
          },
          {
            "type": "galerie",
            "kicker": "Werkhalle",
            "title": "Die Arbeit galt als Frauenarbeit.Die Verantwortung ebenfalls.",
            "body": "",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26368",
                "label": "TFA-7329130-1",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/TFA-7329130-1-300x169.webp",
                "width": "1672",
                "height": "941"
              }
            ],
            "gallery_layout": "viewport"
          },
          {
            "type": "zitat",
            "kicker": "",
            "title": "Förderung und Belehrung",
            "body": "Frauen sollten sich qualifizieren. Zugleich wurden ihre Lern- und Aufstiegsmöglichkeiten oft paternalistisch kommentiert. Die Quellen zeigen Förderung, aber auch Kontrolle und Erwartung.",
            "quote": "",
            "attribution": "",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26370",
                "label": "Quelle zur Qualifizierung im WF, 1960er Jahre",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/126896552_181100043650673_1483559125237673096_n-300x233.webp",
                "width": "1422",
                "height": "1106"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-right"
          },
          {
            "type": "galerie",
            "kicker": "",
            "title": "Anni Gent wird zur Aktivistin",
            "body": "Anni Gent kam 1948 als ungelernte Arbeiterin in die Röhren-Sockelei. Verbesserungsvorschläge, Normerhöhung und Auszeichnungen machten sie früh zu einer öffentlichen Figur des Werks.",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26372",
                "label": "Bundesarchiv_Bild_183-70631-0001_Werk_fuer_Fernmeldewesen_Berlin_Brigadeleiterin-ausschnitt",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/Bundesarchiv_Bild_183-70631-0001_Werk_fuer_Fernmeldewesen_Berlin_Brigadeleiterin-ausschnitt-300x169.webp",
                "width": "1672",
                "height": "941"
              }
            ],
            "gallery_layout": "viewport"
          },
          {
            "type": "zitat",
            "kicker": "",
            "title": "Die „Gent-Mädels“ wurden zur Projektionsfläche.",
            "body": "Presse, Leistung, Erziehung und kollektive Arbeit griffen ineinander.",
            "quote": "",
            "attribution": "",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26375",
                "label": "Artikel-berliner-Zeitung-Ausschnitt-2",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/Artikel-berliner-Zeitung-Ausschnitt-2-300x169.webp",
                "width": "1672",
                "height": "941"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-left"
          },
          {
            "type": "zitat",
            "kicker": "",
            "title": "Anerkennung als Ritual",
            "body": "Frauentag erschien als Feier und Würdigung. Zugleich zeigen die Quellen, welche Rollenbilder im Betrieb bestätigt und öffentlich inszeniert wurden.\n\nFazit",
            "quote": "",
            "attribution": "",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26378",
                "label": "36",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/36-225x300.webp",
                "width": "1086",
                "height": "1448"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-right"
          },
          {
            "type": "schluss",
            "kicker": "",
            "title": "Die Geschichte der Frauen im WF ist keine Randgeschichte.",
            "body": "Die Werkhallen sind verschwunden oder haben ihre Funktion verändert. Geblieben sind Fotografien, Erinnerungen und Dokumente.\n\nAus ihnen entsteht kein vollständiges Bild. Aber genug, um die Frauen wieder sichtbar zu machen, die das Werk über Jahrzehnte mitgetragen haben.",
            "links": [
              {
                "label": "Ort im Atlas",
                "url": "/schoneweide/"
              },
              {
                "label": "Archivbestand",
                "url": "/archiv/"
              },
              {
                "label": "Zeitzeugen",
                "url": "/archiv/?s=Zeitzeugen"
              },
              {
                "label": "Rundgänge",
                "url": "/fuehrungen/"
              },
              {
                "label": "Langfassung",
                "url": "/publikationen/"
              }
            ]
          },
          {
            "type": "objektfokus",
            "kicker": "Fokus",
            "title": "Von Sammlung",
            "body": "",
            "object_refs": [
              {
                "kind": "archive_object",
                "source": "iss-archive",
                "id": "15302",
                "label": "Martha Meya und Kolleginnen",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TFA-537861-2-150x150.jpg",
                "set_id": "27",
                "set_title": "Frauen im Werk - Bildauswahl",
                "member_id": "4543",
                "member_caption": "Das Foto verbindet Arbeitsplatz, Autorinnenschaft und die frühe Sichtbarkeit von Frauen in der Betriebsöffentlichkeit."
              },
              {
                "kind": "archive_object",
                "source": "iss-archive",
                "id": "15363",
                "label": "Vortrag zum Frauentag",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/frauentag-maedchen-beim-vortrag-foto-maerz-1956-64695-1-150x150.jpg",
                "set_id": "27",
                "set_title": "Frauen im Werk - Bildauswahl",
                "member_id": "4544",
                "member_caption": "Ein Mädchen in FDJ-Uniform spricht 1956 zum Frauentag; Feier, Erziehung und Betriebspolitik greifen ineinander."
              },
              {
                "kind": "archive_object",
                "source": "iss-archive",
                "id": "15946",
                "label": "Arbeitsgemeinschaft Pressteller",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TFA-6221514-1-150x150.jpg",
                "set_id": "27",
                "set_title": "Frauen im Werk - Bildauswahl",
                "member_id": "4545",
                "member_caption": "Die Arbeitsgemeinschaft Hartwig zeigt weibliche Produktionsarbeit als kollektive Praxis der frühen 1960er Jahre."
              }
            ]
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 26309,
      "new": true,
      "post": {
        "post_title": "single-ausstellung",
        "post_content": "<!-- wp:template-part {\"slug\":\"header\",\"tagName\":\"header\"} /-->\n\n<!-- wp:group {\"tagName\":\"main\",\"className\":\"iss-ausstellung-page\",\"layout\":{\"type\":\"default\"}} -->\n<main class=\"wp-block-group iss-ausstellung-page\">\n\t<!-- wp:group {\"tagName\":\"section\",\"className\":\"iss-ausstellung-hero\",\"layout\":{\"type\":\"default\"}} -->\n\t<section class=\"wp-block-group iss-ausstellung-hero\">\n\t\t<!-- wp:group {\"className\":\"iss-ausstellung-hero__media\",\"layout\":{\"type\":\"default\"}} -->\n\t\t<div class=\"wp-block-group iss-ausstellung-hero__media\">\n\t\t\t<!-- wp:post-featured-image {\"isLink\":false} /-->\n\t\t</div>\n\t\t<!-- /wp:group -->\n\n\t\t<!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"default\"}} -->\n\t\t<div class=\"wp-block-group iss-container\">\n\t\t\t<!-- wp:group {\"className\":\"iss-ausstellung-hero__row\",\"layout\":{\"type\":\"default\"}} -->\n\t\t\t<div class=\"wp-block-group iss-ausstellung-hero__row\">\n\t\t\t\t<!-- wp:group {\"className\":\"iss-ausstellung-hero__shell\",\"layout\":{\"type\":\"default\"}} -->\n\t\t\t\t<div class=\"wp-block-group iss-ausstellung-hero__shell\">\n\t\t\t\t\t<!-- wp:group {\"className\":\"iss-ausstellung-hero__copy\",\"layout\":{\"type\":\"default\"}} -->\n\t\t\t\t\t<div class=\"wp-block-group iss-ausstellung-hero__copy\">\n\t\t\t\t\t\t<!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker--compact\"} -->\n\t\t\t\t\t\t<p class=\"iss-kicker iss-kicker--compact\">Ausstellung</p>\n\t\t\t\t\t\t<!-- /wp:paragraph -->\n\n\t\t\t\t\t\t<!-- wp:post-title {\"level\":1,\"className\":\"iss-ausstellung-hero__title\"} /-->\n\t\t\t\t\t\t<!-- wp:post-excerpt {\"moreText\":\"\",\"showMoreOnNewLine\":false,\"excerptLength\":28,\"className\":\"iss-ausstellung-hero__lede\"} /-->\n\t\t\t\t\t</div>\n\t\t\t\t\t<!-- /wp:group -->\n\t\t\t\t</div>\n\t\t\t\t<!-- /wp:group -->\n\n\t\t\t\t<!-- wp:group {\"className\":\"iss-front-banner-slot iss-ausstellung-hero__meta-col\",\"layout\":{\"type\":\"default\"}} -->\n\t\t\t\t<div class=\"wp-block-group iss-front-banner-slot iss-ausstellung-hero__meta-col\">\n\t\t\t\t\t<!-- wp:iss/content-meta {\"kicker\":\"Besuch\",\"title\":\"Datum, Öffnungszeiten und Ort\"} /-->\n\t\t\t\t</div>\n\t\t\t\t<!-- /wp:group -->\n\t\t\t</div>\n\t\t\t<!-- /wp:group -->\n\t\t</div>\n\t\t<!-- /wp:group -->\n\t</section>\n\t<!-- /wp:group -->\n\n\t<!-- wp:group {\"tagName\":\"section\",\"className\":\"iss-ausstellung-story\",\"layout\":{\"type\":\"default\"}} -->\n\t<section class=\"wp-block-group iss-ausstellung-story\">\n\t\t<!-- wp:group {\"className\":\"iss-ausstellung-content\",\"layout\":{\"type\":\"default\"}} -->\n\t\t<div class=\"wp-block-group iss-ausstellung-content\">\n\t\t\t<!-- wp:post-content {\"layout\":{\"type\":\"default\"}} /-->\n\t\t</div>\n\t\t<!-- /wp:group -->\n\t</section>\n\t<!-- /wp:group -->\n\n\t<!-- wp:iss/related-content {\"kicker\":\"Weiter entdecken\",\"title\":\"Verwandte Inhalte\",\"postTypes\":[\"register_place\",\"archivbeitrag\",\"fuehrung\",\"publication\",\"veranstaltung\",\"ausstellung\",\"projekt\",\"post\",\"page\",\"atlas_story\",\"archivobjekt\",\"video\",\"archivsammlung\",\"entity_profile\"],\"perPage\":3,\"source\":\"entity\",\"layoutVariant\":\"register\",\"showImage\":false,\"columns\":3,\"skin\":\"exhibition\",\"className\":\"iss-ausstellung-more\"} /-->\n</main>\n<!-- /wp:group -->\n\n<!-- wp:template-part {\"slug\":\"footer\",\"tagName\":\"footer\"} /-->",
        "post_excerpt": "",
        "post_status": "publish",
        "post_name": "single-ausstellung",
        "post_type": "wp_template",
        "post_parent": 0,
        "menu_order": 0,
        "post_mime_type": "",
        "post_date": "2026-06-09 11:42:43",
        "post_date_gmt": "2026-06-09 09:42:43"
      },
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [
          "theme"
        ],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "terms": {
        "wp_theme": [
          {
            "slug": "industriesalon",
            "name": "industriesalon",
            "description": ""
          }
        ]
      }
    },
    {
      "id": 26381,
      "new": false,
      "post": {
        "post_content": "<!-- wp:group {\"className\":\"iss-ausstellung-care-quote\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-care-quote\"><!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>„Jetzt geht es uns gut.“</p>\n<!-- /wp:paragraph --><cite>Bildunterschrift zur Kinderkrippe des HF, Betriebszeitung <em>HF-Sender</em>, Nr. 16, 28. Mai 1954.</cite></blockquote>\n<!-- /wp:quote --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-care-question\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-care-question\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Leitfrage</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Wer betreut die Kinder, wenn die Mütter im Werk arbeiten?</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Die Quellen erzählen von Fürsorge und Entlastung. Gleichzeitig zeigen sie, dass Kinderbetreuung auch eine Bedingung der Produktion war: Frauen sollten arbeiten können, Schichten sollten laufen, eingearbeitete Kräfte sollten im Betrieb bleiben.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-station iss-ausstellung-stationu002du002dsplit\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-station iss-ausstellung-station--split\"><!-- wp:image {\"id\":13824,\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"/wp-content/uploads/2023/12/HF-Sender-16-Titelseite.jpg\" alt=\"\" class=\"wp-image-13824\" /><figcaption class=\"wp-element-caption\">HF-Sender Nr. 16, Titelseite zur Kinderkrippe, 1954.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-station__copy\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-station__copy\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">1 · Kinderkrippe</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Betreuung als Produktionsfrage</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Die frühe Berichterstattung zeigt die Kinderkrippe als liebevollen Ort. Doch der Text sagt auch offen, weshalb der Betrieb sie brauchte: Die Mütter sollten sich „voll und ganz“ für die Erfüllung der Produktionspläne einsetzen können.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p><strong>Quellenauszug:</strong> „Die Muttis wissen, wieviel liebevolle Geduld und rastlose Arbeit die Pflege eines Kleinkindes erfordert …“ — <em>HF-Sender</em>, 1954.</p>\n<!-- /wp:paragraph --></blockquote>\n<!-- /wp:quote --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-care-object-grid\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-care-object-grid\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Objektfokus</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Drei Quellen, drei Blickrichtungen</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Die Ausstellung bindet Archivobjekte direkt ein. Nicht als Kartengitter am Ende, sondern als kleine Beweisstücke mitten in der Erzählung.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":0,\"variant\":\"featured\"} /-->\n\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":5,\"variant\":\"featured\"} /-->\n\n<!-- wp:iss-wf-import/archive-object {\"setId\":13,\"memberPosition\":7,\"variant\":\"featured\"} /--></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-station iss-ausstellung-stationu002du002dfull iss-ausstellung-stationu002du002ddark\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-station iss-ausstellung-station--full iss-ausstellung-station--dark\"><!-- wp:image {\"id\":23627,\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"/wp-content/uploads/2026/05/kinderheim-julius-rosenberg-gartenfront-foto-1962-65505.jpg\" alt=\"\" class=\"wp-image-23627\" /></figure>\n<!-- /wp:image -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-station__caption\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-station__caption\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">2 · Kinderwochenheim</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Montagmorgen gebracht. Freitagnachmittag abgeholt.</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Das Kinderheim „Agnes Smedley“ war zeitweise ein Kinderwochenheim. Neben Tagesgruppen gab es Heimgruppen, in denen Kinder die Woche über blieben. Für heutige Besucher ist das erklärungsbedürftig — und genau deshalb ausstellungsstark.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-care-album\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-care-album\"><!-- wp:group {\"className\":\"iss-ausstellung-care-album__grid\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-care-album__grid\"><!-- wp:group {\"className\":\"iss-ausstellung-care-album__note\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-care-album__note\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Dokumentarische Strecke</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Schlafen. Spielen. Hausaufgaben.</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Eine Ausstellung muss hier nicht alles erklären. Drei bis vier Bilder können zeigen, dass Betreuung aus Routinen bestand: Essen, Schlafen, Lernen, Abholen, Wäsche, Wochenrhythmus.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:image {\"id\":20899,\"sizeSlug\":\"large\",\"className\":\"iss-ausstellung-care-album__photo\"} -->\n<figure class=\"wp-block-image size-large iss-ausstellung-care-album__photo\"><img src=\"/wp-content/uploads/2026/05/TFA-6221489-1.jpg\" alt=\"\" class=\"wp-image-20899\" /><figcaption class=\"wp-element-caption\">Schlafräume im Kinderheim.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":20100,\"sizeSlug\":\"large\",\"className\":\"iss-ausstellung-care-album__photo\"} -->\n<figure class=\"wp-block-image size-large iss-ausstellung-care-album__photo\"><img src=\"/wp-content/uploads/2026/05/drei-kinder-und-erzieherin-im-kinderheim-foto-juli-1954-64518-1.jpg\" alt=\"\" class=\"wp-image-20100\" /><figcaption class=\"wp-element-caption\">Gruppenalltag im Kinderheim.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":13812,\"sizeSlug\":\"large\",\"className\":\"iss-ausstellung-care-album__photo\"} -->\n<figure class=\"wp-block-image size-large iss-ausstellung-care-album__photo\"><img src=\"/wp-content/uploads/2024/01/kinder-im-planschbecken-juni-1977-sw-foto-kurt-schwarz-95017.jpg\" alt=\"\" class=\"wp-image-13812\" /><figcaption class=\"wp-element-caption\">Kinder im Planschbecken, Foto 1977.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"id\":20102,\"sizeSlug\":\"large\",\"className\":\"iss-ausstellung-care-album__photo\"} -->\n<figure class=\"wp-block-image size-large iss-ausstellung-care-album__photo\"><img src=\"/wp-content/uploads/2026/05/TFA-549681-1.jpg\" alt=\"\" class=\"wp-image-20102\" /><figcaption class=\"wp-element-caption\">Szene im Kindergarten des Werkes, 1954.</figcaption></figure>\n<!-- /wp:image --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-station iss-ausstellung-stationu002du002dsplit iss-ausstellung-stationu002du002dreverse\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-station iss-ausstellung-station--split iss-ausstellung-station--reverse\"><!-- wp:image {\"id\":13809,\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"/wp-content/uploads/2024/01/kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-kin.jpg\" alt=\"\" class=\"wp-image-13809\" /><figcaption class=\"wp-element-caption\">Kinder mit Erzieherin am Sandkasten vor dem WF-Gebäude.</figcaption></figure>\n<!-- /wp:image -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-station__copy\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-station__copy\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact iss-ausstellung-station__kicker\"} -->\n<p class=\"iss-kicker iss-kicker--compact iss-ausstellung-station__kicker\">3 · Neue Mühle</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Ein Ort außerhalb der Werkhalle</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Mit „Neue Mühle“ verschiebt sich die Geschichte. Es geht nicht mehr nur um Betreuung während der Arbeitszeit, sondern um eine eigene Kinderwelt: Haus, Gelände, Erholung, Ferien und Ausflüge.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p><strong>Ausstellungsentscheidung:</strong> Dieses Kapitel sollte eher bildhaft als argumentativ sein. Ein großes Foto, kurze Sätze, wenige Daten.</p>\n<!-- /wp:paragraph --></blockquote>\n<!-- /wp:quote --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-care-stat\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-care-stat\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">4 · Maßstab</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-care-stat__grid\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-care-stat__grid\"><!-- wp:html -->\n<div class=\"iss-ausstellung-care-stat__item\"><strong>8.615</strong><span>Beschäftigte im WF im Jahr 1990</span></div>\n<div class=\"iss-ausstellung-care-stat__item\"><strong>3.503</strong><span>davon Frauen</span></div>\n<div class=\"iss-ausstellung-care-stat__item\"><strong>1.754</strong><span>Frauen mit Kindern</span></div>\n<!-- /wp:html --></div>\n<!-- /wp:group -->\n\n<!-- wp:paragraph -->\n<p>Die Zahlen holen die Ausstellung zurück in den Betrieb. Kinderbetreuung war kein Randthema. Sie betraf den Alltag vieler Familien und die Arbeitsfähigkeit eines großen Industriebetriebs.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-care-essay\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-care-essay\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">5 · Für wen?</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Wochenheime waren nicht für alle gleich.</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Die Quellen mahnen zur Vorsicht vor schnellen Urteilen. Wochenkrippen und Kinderwochenheime wirkten damals nicht nur als staatliche Zumutung, sondern auch als Lösung für Alleinerziehende, Studierende, Schichtarbeitende und Familien ohne andere Betreuung.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Gerade deshalb sollte die Ausstellung die Spannung halten: Entlastung und Zumutung, Fürsorge und Produktionsinteresse, Schutzraum und Trennung.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph {\"className\":\"iss-ausstellung-small-source\"} -->\n<p class=\"iss-ausstellung-small-source\">Quellenhintergrund: Folge 8 der Serie behandelt die Frage, für wen Wochenkrippen und Kinderwochenheime gedacht waren.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-station iss-ausstellung-stationu002du002dfull iss-ausstellung-stationu002du002ddark\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-station iss-ausstellung-station--full iss-ausstellung-station--dark\"><!-- wp:image {\"id\":20899,\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"/wp-content/uploads/2026/05/TFA-6221489-1.jpg\" alt=\"\" class=\"wp-image-20899\" /></figure>\n<!-- /wp:image -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-station__caption\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-station__caption\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">6 · Ambivalenz</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Fürsorge genügt nicht, wenn Bindung fehlt.</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Spätere Untersuchungen diskutierten mögliche Folgen von Wochenkrippen und Wochenheimen. Die Ausstellung muss diesen Teil nicht dramatisieren, aber sie sollte ihn nicht aussparen.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-ausstellung-conclusion\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-ausstellung-conclusion\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kickeru002du002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Schluss</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Die Kinderbetreuung im WF war mehr als Sozialleistung.</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Sie führte vom Werkstor in Krippenräume, Wochenheime und Ferienorte. Sie half Müttern, im Betrieb zu bleiben. Sie griff tief in den Familienalltag ein. Die Quellen zeigen eine Geschichte von Fürsorge, Arbeit, Organisation und Widerspruch.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:navigation {\"overlayMenu\":\"never\",\"className\":\"iss-ausstellung-research\",\"layout\":{\"type\":\"flex\",\"orientation\":\"horizontal\"}} -->\n<!-- wp:navigation-link {\"label\":\"Archivbestand\",\"url\":\"/archiv/?s=Kinder\",\"kind\":\"custom\"} /-->\n\n<!-- wp:navigation-link {\"label\":\"Kinder im WF\",\"url\":\"/ausstellungen/kinder-im-wf/\",\"kind\":\"custom\"} /-->\n\n<!-- wp:navigation-link {\"label\":\"Weitere Ausstellungen\",\"url\":\"/ausstellungen/\",\"kind\":\"custom\"} /-->\n<!-- /wp:navigation --></div>\n<!-- /wp:group -->"
      },
      "meta": {
        "_thumbnail_id": [
          "13809"
        ],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "1"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": {
        "post_author": "6b86b273ff34fce19d6b804eff5a3f5747ada4eaa22f1d49c01e52ddb7875b4b",
        "post_date": "b8c83104eef1ec01e901b247ae7985497ec6fc84df5fe7f0a13176c98f4e1e43",
        "post_content": "4178a72a1783d3b63bb62934d6bc0a9a25dfe99849ed846512be3303fff41fb6",
        "post_title": "183467358263dff390f6138ea67cb26fc45e6ecedcacd7ee8a0619da3b5b609d",
        "post_excerpt": "fc339f3f1cb67f44cb2711686f422c828768348769b7881e846738d05bb7ea1a",
        "post_status": "a5d47a4311d759db69e576d9eedd6a02fcfd9cd214129fa8492ee1e9c7343def",
        "comment_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "ping_status": "c3eefb58d7c42440a9d4abec51d629544d635a6d936ff3c4d3fca96d611b3cf3",
        "post_name": "b2a0779eeb170db88834cbea23d59cfedc75774d074d380d197141ce1422fde0",
        "post_parent": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "menu_order": "5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9",
        "post_type": "a3462fe2f67dc6d5e4db0c2eadb1440a45e4d71478580429417b9da1cfa54483",
        "post_mime_type": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
      },
      "expected_meta": {
        "_thumbnail_id": [
          "a71d31f242e364b5b205207cd8d7b00ffe1d07effca7c8a399ad8ababfb803ce"
        ],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [
          "5e24b06cdb48422aa534ed50a3523c09290c6fd754cc372abf14eb5c7ca739a2"
        ],
        "_iss_editorial_enabled_ausstellung": [
          "391552c099c101b131feaf24c5795a6a15bc8ec82015424e0d2b4274a369a0bf"
        ],
        "_iss_editorial_ausstellung_skin": []
      },
      "identity": {
        "type": "ausstellung",
        "slug": "kinder-im-werk"
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "objektalbum",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "zitat",
            "kicker": "Ausstellung",
            "title": "Kinder im Werk",
            "body": "Kinderkrippe, Wochenheim, Ferienlager und Familienalltag: Quellen zur Frage, wie das Werk für Fernmeldewesen Arbeit und Betreuung miteinander verband. ",
            "quote": "",
            "attribution": "",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26605",
                "label": "Kinder mit Erzieherin am Sandkasten vor dem WF-Gebäude",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-300x284.webp",
                "width": "1288",
                "height": "1221"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-left"
          },
          {
            "type": "zitat",
            "kicker": "Auftakt",
            "title": "",
            "body": "",
            "quote": "Jetzt geht es uns gut.",
            "attribution": "Bildunterschrift zur Kinderkrippe des HF, Betriebszeitung HF-Sender, Nr. 16, 28. Mai 1954.",
            "object_refs": [],
            "media_refs": [],
            "quote_treatment": "pull",
            "orientation": "media-left"
          },
          {
            "type": "leitfrage",
            "kicker": "Leitfrage",
            "title": "Wer betreut die Kinder, wenn die Mütter im Werk arbeiten?",
            "body": "Die Quellen erzählen von Fürsorge und Entlastung. Gleichzeitig zeigen sie, dass Kinderbetreuung auch eine Bedingung der Produktion war: Frauen sollten arbeiten können, Schichten sollten laufen, eingearbeitete Kräfte sollten im Betrieb bleiben."
          },
          {
            "type": "zitat",
            "kicker": "1 · Kinderkrippe",
            "title": "Betreuung als Produktionsfrage",
            "body": "Die frühe Berichterstattung zeigt die Kinderkrippe als liebevollen Ort. Doch der Text sagt auch offen, weshalb der Betrieb sie brauchte: Die Mütter sollten sich voll und ganz für die Erfüllung der Produktionspläne einsetzen können.",
            "quote": "Die Muttis wissen, wieviel liebevolle Geduld und rastlose Arbeit die Pflege eines Kleinkindes erfordert …",
            "attribution": "HF-Sender, 1954",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26607",
                "label": "1_WFS-1954-16-5f33ff60b3889",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/1_WFS-1954-16-5f33ff60b3889-300x275.webp",
                "width": "1309",
                "height": "1201"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-left"
          },
          {
            "type": "objektfokus",
            "kicker": "Objektfokus",
            "title": "Drei Quellen, drei Blickrichtungen",
            "body": "",
            "object_refs": [
              {
                "kind": "archive_object",
                "source": "iss-archive",
                "id": "26215",
                "label": "HF-Sender Nr. 16: Titelseite zur Kinderkrippe",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2023/12/HF-Sender-16-Titelseite-300x276.jpg",
                "set_id": "13",
                "set_title": "Kinder im WF - Bildauswahl"
              },
              {
                "kind": "archive_object",
                "source": "iss-archive",
                "id": "26217",
                "label": "Kinder mit Erzieherin am Sandkasten vor dem WF-Gebäude",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2024/01/kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-kin-300x284.jpg",
                "set_id": "13",
                "set_title": "Kinder im WF - Bildauswahl"
              },
              {
                "kind": "archive_object",
                "source": "iss-archive",
                "id": "26219",
                "label": "BVV-Unterlage zur Kinderbetreuung, 1973",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2024/02/BVV-1973-236x300.jpg",
                "set_id": "13",
                "set_title": "Kinder im WF - Bildauswahl"
              }
            ]
          },
          {
            "type": "zitat",
            "kicker": "2 · Kinderwochenheim",
            "title": "Montagmorgen gebracht. Freitagnachmittag abgeholt.",
            "body": "Das Kinderheim Agnes Smedley war zeitweise ein Kinderwochenheim. Neben Tagesgruppen gab es Heimgruppen, in denen Kinder die Woche über blieben. Für heutige Besucher ist das erklärungsbedürftig ",
            "quote": "",
            "attribution": "",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "23627",
                "label": "Kinderheim Julius Rosenberg, Gartenfront; Foto 1962",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/kinderheim-julius-rosenberg-gartenfront-foto-1962-65505-300x229.jpg",
                "width": "1000",
                "height": "763"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-right"
          },
          {
            "type": "galerie",
            "kicker": "Dokumentarische Strecke",
            "title": "Schlafen. Spielen. Hausaufgaben.",
            "body": "",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "20899",
                "label": "Schlafräume im Kinderheim.",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TFA-6221489-1-300x224.jpg",
                "width": "400",
                "height": "298"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "20100",
                "label": "Gruppenalltag im Kinderheim.",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/drei-kinder-und-erzieherin-im-kinderheim-foto-juli-1954-64518-1-300x206.jpg",
                "width": "1000",
                "height": "687"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "13812",
                "label": "Kinder im Planschbecken, Foto 1977.",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2024/01/kinder-im-planschbecken-juni-1977-sw-foto-kurt-schwarz-95017-300x200.jpg",
                "width": "960",
                "height": "640"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "20102",
                "label": "Szene im Kindergarten des Werkes, 1954.",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TFA-549681-1-300x205.jpg",
                "width": "400",
                "height": "273"
              }
            ],
            "gallery_layout": "sequence"
          },
          {
            "type": "zitat",
            "kicker": "3 · Neue Mühle",
            "title": "Ein Ort außerhalb der Werkhalle",
            "body": "Mit Neue Mühle verschiebt sich die Geschichte. Es geht nicht mehr nur um Betreuung während der Arbeitszeit, sondern um eine eigene Kinderwelt: Haus, Gelände, Erholung, Ferien und Ausflüge.",
            "quote": "",
            "attribution": "Ausstellungsentscheidung",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26609",
                "label": "1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585-300x300.webp",
                "width": "1254",
                "height": "1254"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-left"
          },
          {
            "type": "facts",
            "kicker": "4 · Maßstab",
            "title": "Kinderbetreuung war kein Randthema.",
            "body": "<strong>8.615</strong> Beschäftigte im WF im Jahr 1990\n\n<strong>3.503</strong> davon Frauen\n\n<strong>1.754</strong> Frauen mit Kindern\n\n",
            "facts": []
          },
          {
            "type": "fliesstext",
            "kicker": "5 · Für wen?",
            "title": "Wochenheime waren nicht für alle gleich.",
            "body": "Die Quellen mahnen zur Vorsicht vor schnellen Urteilen. Wochenkrippen und Kinderwochenheime wirkten damals nicht nur als staatliche Zumutung, sondern auch als Lösung für Alleinerziehende, Studierende, Schichtarbeitende und Familien ohne andere Betreuung.\n\nGerade deshalb sollte die Ausstellung die Spannung halten: Entlastung und Zumutung, Fürsorge und Produktionsinteresse, Schutzraum und Trennung.\n\nQuellenhintergrund: Folge 8 der Serie behandelt die Frage, für wen Wochenkrippen und Kinderwochenheime gedacht waren."
          },
          {
            "type": "zitat",
            "kicker": "6 · Ambivalenz",
            "title": "Fürsorge genügt nicht, wenn Bindung fehlt.",
            "body": "Spätere Untersuchungen diskutierten mögliche Folgen von Wochenkrippen und Wochenheimen. ",
            "quote": "",
            "attribution": "",
            "object_refs": [],
            "media_refs": [
              {
                "kind": "media",
                "source": "wp-media",
                "id": "20899",
                "label": "Kinderheim Julius Rosenberg, Kinderschlafsaal; Foto 1962",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/05/TFA-6221489-1-300x224.jpg",
                "width": "400",
                "height": "298"
              },
              {
                "kind": "media",
                "source": "wp-media",
                "id": "26610",
                "label": "1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw",
                "thumbnail": "{{SITE_URL}}/wp-content/uploads/2026/06/1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw-300x300.webp",
                "width": "960",
                "height": "959"
              }
            ],
            "quote_treatment": "source",
            "orientation": "media-right"
          },
          {
            "type": "schluss",
            "kicker": "Schluss",
            "title": "Die Kinderbetreuung im WF war mehr als Sozialleistung.",
            "body": "Sie führte vom Werkstor in Krippenräume, Wochenheime und Ferienorte. Sie half Müttern, im Betrieb zu bleiben. Sie griff tief in den Familienalltag ein. Die Quellen zeigen eine Geschichte von Fürsorge, Arbeit, Organisation und Widerspruch.",
            "links": [
              {
                "label": "Archivbestand",
                "url": "/archiv/?s=Kinder"
              },
              {
                "label": "Kinder im WF",
                "url": "/ausstellungen/kinder-im-wf/"
              },
              {
                "label": "Weitere Ausstellungen",
                "url": "/ausstellungen/"
              }
            ]
          }
        ],
        "deleted_sections": []
      },
      "enabled": true
    },
    {
      "id": 26532,
      "new": true,
      "post": {
        "post_title": "page-projekte",
        "post_content": "<!-- wp:template-part {\"slug\":\"header\",\"theme\":\"industriesalon\",\"tagName\":\"header\"} /-->\n\n<!-- wp:group {\"tagName\":\"main\",\"className\":\"iss-projects-page iss-scheme-green\",\"layout\":{\"type\":\"default\"}} -->\n<main class=\"wp-block-group iss-projects-page iss-scheme-green\"><!-- wp:group {\"tagName\":\"section\",\"className\":\"section iss-projects-hero\",\"layout\":{\"type\":\"default\"}} -->\n<section class=\"wp-block-group section iss-projects-hero\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:group {\"className\":\"iss-projects-hero__grid\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-projects-hero__grid\"><!-- wp:group {\"className\":\"iss-projects-hero__intro iss-heading iss-heading\\u002d\\u002duncaged\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-projects-hero__intro iss-heading iss-heading--uncaged\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact iss-kicker\\u002d\\u002dlight\"} -->\n<p class=\"iss-kicker iss-kicker--compact iss-kicker--light\">Projekte</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":1,\"className\":\"iss-heading__title iss-projects-hero__title\"} -->\n<h1 class=\"wp-block-heading iss-heading__title iss-projects-hero__title\">Projekte</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-heading__text iss-projects-hero__lead\"} -->\n<p class=\"iss-heading__text iss-projects-hero__lead\">Projekte machen sichtbar, woran der Industriesalon derzeit arbeitet. Sie bündeln Forschung, Sammlungsarbeit, Kooperationen und Entwicklungsprozesse rund um Schöneweide und seine Industriegeschichte</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-projects-hero__rail\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-projects-hero__rail\"><!-- wp:paragraph {\"className\":\"iss-projects-hero__rail-label\"} -->\n<p class=\"iss-projects-hero__rail-label\">Lesart</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list {\"className\":\"is-style-iss-list-rail iss-projects-hero__rail-list\"} -->\n<ul class=\"wp-block-list is-style-iss-list-rail iss-projects-hero__rail-list\"><!-- wp:list-item -->\n<li><strong>Status:</strong> Vorbereitung oder Abschluss.</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li><strong>Orte:</strong> verknüpfte Projektorte.</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li><strong>Material:</strong> Bilder, PDFs, Quellen.</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph {\"className\":\"iss-projects-hero__rail-links\"} -->\n<p class=\"iss-projects-hero__rail-links\"><a class=\"iss-action-link\" href=\"#kommende-projekte\">Kommende Projekte</a> <a class=\"iss-action-link\" href=\"#projektindex\">Zum Index</a></p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group -->\n\n<!-- wp:group {\"tagName\":\"section\",\"className\":\"section section\\u002d\\u002dplain iss-projects-featured\",\"layout\":{\"type\":\"default\"},\"anchor\":\"kommende-projekte\"} -->\n<section id=\"kommende-projekte\" class=\"wp-block-group section section--plain iss-projects-featured\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:group {\"className\":\"iss-projects-featured__head iss-heading iss-heading\\u002d\\u002duncaged\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-projects-featured__head iss-heading iss-heading--uncaged\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Kommende Projekte</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"className\":\"iss-heading__title\"} -->\n<h2 class=\"wp-block-heading iss-heading__title\">In Vorbereitung</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-heading__text\"} -->\n<p class=\"iss-heading__text\">Die zwei nächsten Vorhaben stehen als volle Projektzeilen vor dem Archiv: zuerst der Arbeitsstand, danach die dokumentierten Dossiers.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:query {\"queryId\":13,\"query\":{\"perPage\":2,\"pages\":0,\"offset\":0,\"postType\":\"projekt\",\"order\":\"asc\",\"orderBy\":\"menu_order\",\"inherit\":false},\"className\":\"iss-featured-projects__query\"} -->\n<div class=\"wp-block-query iss-featured-projects__query\"><!-- wp:post-template {\"className\":\"iss-featured-projects__items\"} -->\n<!-- wp:group {\"tagName\":\"article\",\"className\":\"iss-project-index-card\",\"layout\":{\"type\":\"default\"}} -->\n<article class=\"wp-block-group iss-project-index-card\"><!-- wp:post-featured-image {\"isLink\":true,\"scale\":\"contain\",\"className\":\"iss-project-index-card__media\"} /-->\n\n<!-- wp:group {\"className\":\"iss-project-index-card__body\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-project-index-card__body\"><!-- wp:group {\"className\":\"iss-project-index-card__meta\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-project-index-card__meta\"><!-- wp:iss/project-status {\"className\":\"iss-project-index-card__terms iss-project-index-card__terms\\u002d\\u002dstatus\"} /-->\n\n<!-- wp:post-terms {\"term\":\"iss_topic\",\"separator\":\" · \",\"className\":\"iss-project-index-card__terms iss-project-index-card__terms\\u002d\\u002dtopics\"} /--></div>\n<!-- /wp:group -->\n\n<!-- wp:post-title {\"level\":3,\"isLink\":true,\"className\":\"iss-project-index-card__title\"} /-->\n\n<!-- wp:post-excerpt {\"moreText\":\"Projekt öffnen\",\"excerptLength\":42,\"className\":\"iss-project-index-card__excerpt\"} /-->\n\n<!-- wp:iss/related-place-links {\"perPage\":3,\"showRole\":false} /--></div>\n<!-- /wp:group --></article>\n<!-- /wp:group -->\n<!-- /wp:post-template -->\n\n<!-- wp:query-no-results -->\n<!-- wp:group {\"className\":\"iss-project-index__empty\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-project-index__empty\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Keine Einträge</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Es sind noch keine kommenden Projekte veröffentlicht.</h3>\n<!-- /wp:heading --></div>\n<!-- /wp:group -->\n<!-- /wp:query-no-results --></div>\n<!-- /wp:query --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group -->\n\n<!-- wp:group {\"tagName\":\"section\",\"className\":\"section section\\u002d\\u002dplain iss-project-index\",\"layout\":{\"type\":\"default\"},\"anchor\":\"projektindex\"} -->\n<section id=\"projektindex\" class=\"wp-block-group section section--plain iss-project-index\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:group {\"className\":\"iss-project-index__head iss-heading iss-heading\\u002d\\u002duncaged\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-project-index__head iss-heading iss-heading--uncaged\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Index</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"className\":\"iss-heading__title\"} -->\n<h2 class=\"wp-block-heading iss-heading__title\">Weitere Projekt-Dossiers</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-heading__text\"} -->\n<p class=\"iss-heading__text\">Hier folgen die übrigen Projekt-Dossiers. Status und Themen stehen direkt am Eintrag.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:query {\"queryId\":12,\"query\":{\"perPage\":24,\"pages\":0,\"offset\":2,\"postType\":\"projekt\",\"order\":\"asc\",\"orderBy\":\"menu_order\",\"inherit\":false},\"className\":\"iss-project-index__query\"} -->\n<div class=\"wp-block-query iss-project-index__query\"><!-- wp:post-template {\"className\":\"iss-project-index__items\"} -->\n<!-- wp:group {\"tagName\":\"article\",\"className\":\"iss-project-index-card\",\"layout\":{\"type\":\"default\"}} -->\n<article class=\"wp-block-group iss-project-index-card\"><!-- wp:post-featured-image {\"isLink\":true,\"className\":\"iss-project-index-card__media\"} /-->\n\n<!-- wp:group {\"className\":\"iss-project-index-card__body\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-project-index-card__body\"><!-- wp:group {\"className\":\"iss-project-index-card__meta\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-project-index-card__meta\"><!-- wp:iss/project-status {\"className\":\"iss-project-index-card__terms iss-project-index-card__terms\\u002d\\u002dstatus\"} /-->\n\n<!-- wp:post-terms {\"term\":\"iss_topic\",\"separator\":\" · \",\"className\":\"iss-project-index-card__terms iss-project-index-card__terms\\u002d\\u002dtopics\"} /--></div>\n<!-- /wp:group -->\n\n<!-- wp:post-title {\"level\":3,\"isLink\":true,\"className\":\"iss-project-index-card__title\"} /-->\n\n<!-- wp:post-excerpt {\"moreText\":\"Projekt öffnen\",\"excerptLength\":34,\"className\":\"iss-project-index-card__excerpt\"} /-->\n\n<!-- wp:iss/related-place-links {\"perPage\":3,\"showRole\":false} /--></div>\n<!-- /wp:group --></article>\n<!-- /wp:group -->\n<!-- /wp:post-template -->\n\n<!-- wp:query-no-results -->\n<!-- wp:group {\"className\":\"iss-project-index__empty\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-project-index__empty\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Keine Einträge</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Es sind noch keine Projekte veröffentlicht.</h3>\n<!-- /wp:heading --></div>\n<!-- /wp:group -->\n<!-- /wp:query-no-results --></div>\n<!-- /wp:query --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group -->\n\n<!-- wp:group {\"tagName\":\"section\",\"className\":\"section section\\u002d\\u002dplain iss-projects-contact\",\"layout\":{\"type\":\"default\"}} -->\n<section class=\"wp-block-group section section--plain iss-projects-contact\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:group {\"className\":\"iss-projects-contact__inner\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-projects-contact__inner\"><!-- wp:group {\"className\":\"iss-heading iss-heading\\u002d\\u002duncaged\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-heading iss-heading--uncaged\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Neue Vorhaben</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"className\":\"iss-heading__title\"} -->\n<h2 class=\"wp-block-heading iss-heading__title\">Projektideen gehören in die Struktur, nicht daneben.</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-heading__text\"} -->\n<p class=\"iss-heading__text\">Wenn ein neues Vorhaben entsteht, reicht ein Projekt-Dossier als Anfang: kurzer Einstieg, Status, Orte und Material. Ausführlichere Texte können später in Kapitel wachsen.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"iss-projects-contact__data\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-projects-contact__data\"><!-- wp:paragraph {\"className\":\"iss-projects-contact__label\"} -->\n<p class=\"iss-projects-contact__label\">Kontakt</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:industriesalon/field {\"key\":\"contact.email\",\"tagName\":\"p\",\"linkMode\":\"email\",\"label\":\"E-Mail:\",\"hrefSuffix\":\"?subject=Projektidee\"} /--></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group --></main>\n<!-- /wp:group -->\n\n<!-- wp:template-part {\"slug\":\"footer\",\"theme\":\"industriesalon\",\"tagName\":\"footer\"} /-->",
        "post_excerpt": "",
        "post_status": "publish",
        "post_name": "page-projekte",
        "post_type": "wp_template",
        "post_parent": 0,
        "menu_order": 0,
        "post_mime_type": "",
        "post_date": "2026-06-14 21:26:39",
        "post_date_gmt": "2026-06-14 19:26:39"
      },
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [
          "theme"
        ],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "terms": {
        "wp_theme": [
          {
            "slug": "industriesalon",
            "name": "industriesalon",
            "description": ""
          }
        ]
      }
    },
    {
      "id": 26560,
      "new": true,
      "post": {
        "post_title": "page-publikationen",
        "post_content": "<!-- wp:template-part {\"slug\":\"header\",\"theme\":\"industriesalon\",\"tagName\":\"header\"} /-->\n\n<!-- wp:group {\"tagName\":\"main\",\"className\":\"iss-publications-page iss-publications-page\\u002d\\u002dbrowser iss-scheme-blue\",\"layout\":{\"type\":\"default\"}} -->\n<main class=\"wp-block-group iss-publications-page iss-publications-page--browser iss-scheme-blue\"><!-- wp:group {\"tagName\":\"section\",\"className\":\"section section\\u002d\\u002dplain iss-publications-masthead\",\"layout\":{\"type\":\"default\"}} -->\n<section class=\"wp-block-group section section--plain iss-publications-masthead\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:group {\"className\":\"iss-publications-masthead__grid\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-publications-masthead__grid\"><!-- wp:group {\"className\":\"iss-publications-masthead__intro iss-heading iss-heading\\u002d\\u002duncaged\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-publications-masthead__intro iss-heading iss-heading--uncaged\"><!-- wp:paragraph {\"className\":\"iss-kicker iss-kicker\\u002d\\u002dcompact\"} -->\n<p class=\"iss-kicker iss-kicker--compact\">Publikationen</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":1,\"className\":\"iss-heading__title iss-publications-masthead__title\"} -->\n<h1 class=\"wp-block-heading iss-heading__title iss-publications-masthead__title\">Publikationen</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"iss-heading__text iss-publications-masthead__lead\"} -->\n<p class=\"iss-heading__text iss-publications-masthead__lead\">Publikationen</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Schöneweide lässt sich nicht nur vor Ort erleben, sondern auch lesen. Broschüren, Chroniken, Fotoalben und längere Beiträge führen durch Gebäude, Betriebe, Produkte, Arbeitswelten und die Geschichte des Stadtteils.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Die Publikationen greifen Materialien aus Archiv, Sammlung und Forschung auf und verbinden sie mit Fotografien, Karten, Zeitzeugenberichten und aktuellen Fragestellungen. Sie entstehen aus der Arbeit des Industriesalon und führen Themen weiter, die in Ausstellungen, Rundgängen oder Projekten begonnen haben.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n<!-- wp:iss/featured-publication /--></div>\n<!-- /wp:group --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group -->\n\n<!-- wp:spacer -->\n<div style=\"height:100px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->\n\n<!-- wp:group {\"tagName\":\"section\",\"className\":\"section section\\u002d\\u002dplain iss-publications-browser-section\",\"layout\":{\"type\":\"default\"}} -->\n<section class=\"wp-block-group section section--plain iss-publications-browser-section\"><!-- wp:group {\"className\":\"iss-container\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group iss-container\"><!-- wp:iss/publications-browser {\"copy\":{\"ariaLabel\":\"Publikationsbestand\",\"filterAllLabel\":\"Alle Themen\",\"emptyText\":\"Keine Publikationen passen zu dieser Auswahl.\",\"sectionCountSingular\":\"Eintrag\",\"sectionCountPlural\":\"Einträge\",\"discoveryTitle\":\"Weiterentdecken\"},\"formatCopy\":{\"standard\":{\"label\":\"Publikationen\"},\"timeline\":{\"label\":\"Chroniken\"},\"longread\":{\"label\":\"Longreads\"},\"photoalbum\":{\"label\":\"Fotoalben\"}},\"filterCopy\":{\"shared_topic\":{\"label\":\"Thema\"}}} -->\n<!-- wp:iss/publications-browser-sidebar {\"kicker\":\"Bestand\",\"title\":\"Publikationsformen\"} /-->\n\n<!-- wp:iss/publications-browser-results {\"kicker\":\"Thema\",\"title\":\"Themen im Bestand\"} -->\n<!-- wp:iss/publications-browser-format {\"title\":\"Publikationen\",\"description\":\"Broschüren, Hefte und klassische Publikationsseiten.\"} /-->\n\n<!-- wp:iss/publications-browser-format {\"format\":\"timeline\",\"title\":\"Chroniken\",\"description\":\"Zeitleisten und Entwicklungsgeschichten mit datierten Stationen.\"} /-->\n\n<!-- wp:iss/publications-browser-format {\"format\":\"longread\",\"title\":\"Longreads\",\"description\":\"Kapitelbasierte Online-Publikationen und Quellengeschichten.\"} /-->\n\n<!-- wp:iss/publications-browser-format {\"format\":\"photoalbum\",\"title\":\"Fotoalben\",\"description\":\"Bildfolgen und Albumstrecken aus dem Bestand.\"} /-->\n<!-- /wp:iss/publications-browser-results -->\n<!-- /wp:iss/publications-browser --></div>\n<!-- /wp:group --></section>\n<!-- /wp:group --></main>\n<!-- /wp:group -->\n\n<!-- wp:template-part {\"slug\":\"footer\",\"theme\":\"industriesalon\",\"tagName\":\"footer\"} /-->",
        "post_excerpt": "",
        "post_status": "publish",
        "post_name": "page-publikationen",
        "post_type": "wp_template",
        "post_parent": 0,
        "menu_order": 0,
        "post_mime_type": "",
        "post_date": "2026-06-17 13:30:05",
        "post_date_gmt": "2026-06-17 11:30:05"
      },
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [
          "theme"
        ],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "terms": {
        "wp_theme": [
          {
            "slug": "industriesalon",
            "name": "industriesalon",
            "description": ""
          }
        ]
      }
    },
    {
      "id": 26792,
      "new": true,
      "post": {
        "post_title": "NAG-Fahrzeuge aus Schöneweide",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "publish",
        "post_name": "nag-fahrzeuge-aus-schoneweide",
        "post_type": "ausstellung",
        "post_parent": 0,
        "menu_order": 0,
        "post_mime_type": "",
        "post_date": "2026-06-29 12:30:49",
        "post_date_gmt": "2026-06-29 10:30:49"
      },
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [
          "1"
        ],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_ausstellung": [],
        "_iss_editorial_enabled_ausstellung": [],
        "_iss_editorial_ausstellung_skin": []
      },
      "terms": {
        "category": [],
        "post_tag": [],
        "ausstellung_typ": [
          {
            "slug": "sonderausstellung",
            "name": "Sonderausstellung",
            "description": ""
          }
        ],
        "sammlungsbereich": [],
        "iss_topic": [],
        "iss_place_ref": []
      },
      "format": "ausstellung",
      "document": {
        "schema_version": 1,
        "skin": "typografisch",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "kapitel",
            "kicker": "",
            "title": "",
            "body": "Unter der Leitung von Emil Rathenau begann der Automobilbau im Kabelwerk Oberspree (KWO). Ab 1901 produzierte die \"Neue Automobilgesellschaft\" (NAG) Kraftfahrzeuge, Busse, Limousinen und Elektrofahrzeuge. - Die Ausstellung entsteht in Kooperation mit dem Archiv Axel Oskar Mathieu, www.archiv-axel-oskar-mathieu.de ",
            "links": [],
            "section_treatment": "aside"
          }
        ],
        "deleted_sections": []
      },
      "enabled": false
    },
    {
      "id": 26814,
      "new": true,
      "post": {
        "post_title": "evtl. TXL-Projekt im Salon! Nähere Infos kommen.... schon mal Zeitraum geblockt",
        "post_content": "",
        "post_excerpt": " TXL-Projekt im Salon! Nähere Infos kommen.... schon mal Zeitraum geblockt (Vortrag im Salon + Führung durchs Gelände mit Fokus auf Architektur+Geschichte)",
        "post_status": "publish",
        "post_name": "evtl-txl-projekt-im-salon-nahere-infos-kommen-schon-mal-zeitraum-geblockt",
        "post_type": "veranstaltung",
        "post_parent": 0,
        "menu_order": 0,
        "post_mime_type": "",
        "post_date": "2026-06-29 19:00:05",
        "post_date_gmt": "2026-06-29 17:00:05"
      },
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [
          "event.general"
        ]
      },
      "expected_fields": null,
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_content_json": [],
        "_iss_editorial_enabled_veranstaltung": [],
        "_iss_editorial_veranstaltung_skin": []
      },
      "terms": {
        "category": [],
        "post_tag": [],
        "iss_topic": [],
        "veranstaltung_art": [
          {
            "slug": "workshop",
            "name": "Workshop",
            "description": ""
          }
        ],
        "iss_place_ref": [
          {
            "slug": "place-17960",
            "name": "Industriesalon Schöneweide /",
            "description": ""
          }
        ]
      },
      "format": "veranstaltung",
      "document": {
        "schema_version": 1,
        "skin": "typografisch",
        "variant": "standard",
        "features": [],
        "sections": [
          {
            "type": "upload_intake",
            "kicker": "Euren Daten hier lassen",
            "title": "",
            "body": ""
          }
        ],
        "deleted_sections": [],
        "entity_key": "event.general"
      },
      "enabled": true
    },
    {
      "id": 27388,
      "new": true,
      "post": {
        "post_title": "Rückblick: Repair-Café",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "publish",
        "post_name": "ruckblick-repair-cafe",
        "post_type": "rueckblick",
        "post_parent": 0,
        "menu_order": 0,
        "post_mime_type": "",
        "post_date": "2026-09-11 14:12:38",
        "post_date_gmt": "2026-09-11 12:12:38"
      },
      "meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [
          "default"
        ],
        "origin": [],
        "_iss_report_date": [
          ""
        ],
        "_iss_upload_open": [
          "0"
        ],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": []
      },
      "expected_fields": null,
      "expected_meta": {
        "_thumbnail_id": [],
        "_wp_page_template": [],
        "origin": [],
        "_iss_report_date": [],
        "_iss_upload_open": [],
        "iss_public_overview_enabled": [],
        "iss_archive_browser_lock_field": [],
        "iss_archive_browser_lock_source": [],
        "iss_archive_browser_show_source_cards": [],
        "_iss_publication_pages": [],
        "_iss_publication_price_cents": [],
        "_iss_entity_key": [],
        "_iss_editorial_rueckblick": [],
        "_iss_editorial_enabled_rueckblick": [],
        "_iss_editorial_rueckblick_skin": []
      },
      "terms": {
        "category": [],
        "post_tag": [],
        "iss_topic": [],
        "iss_place_ref": []
      },
      "format": "rueckblick",
      "document": {
        "schema_version": 1,
        "skin": "chronik",
        "variant": "standard",
        "features": [],
        "sections": [],
        "deleted_sections": []
      },
      "enabled": true
    }
  ],
  "media": {
    "2026/04/treskowbruecke-gasanstalt-oberspree-1926-1024x696.webp": {
      "size": 187142,
      "sha256": "de32ce9b451515dc7bf7bd5fa5ac88846dde0aff2d2309ffedef74c716809699"
    },
    "2026/04/treskowbruecke-gasanstalt-oberspree-1926-150x150.webp": {
      "size": 8826,
      "sha256": "99b91d8b65ad006840796079b7de2fc23f962991019e4f5066a10f169e54cdb2"
    },
    "2026/04/treskowbruecke-gasanstalt-oberspree-1926-300x204.webp": {
      "size": 21462,
      "sha256": "796a3581e782c2427e5741f8a0595674f5a0ee8306bb5bb23e84ff49089d7df9"
    },
    "2026/04/treskowbruecke-gasanstalt-oberspree-1926-768x522.webp": {
      "size": 121176,
      "sha256": "68ed429f8ca28c38ba1e71745ac21cd2b1f86c0cece9698f7b8eca80dfbbb378"
    },
    "2026/04/treskowbruecke-gasanstalt-oberspree-1926.webp": {
      "size": 493150,
      "sha256": "8a17bfff7f5f472adee38798c28b893c66dd36f4bcd7e8c92a601b296f75056d"
    },
    "2026/06/1_WFS-1954-16-5f33ff60b3889-1024x940.webp": {
      "size": 346458,
      "sha256": "9809fffa68499357359334fa1f9323c20aa422beadd05639de27793226ac3444"
    },
    "2026/06/1_WFS-1954-16-5f33ff60b3889-150x150.webp": {
      "size": 7404,
      "sha256": "7291849de8bdd2197109e6894f6c4470d92af75970fefc72f6c302562b10cdd4"
    },
    "2026/06/1_WFS-1954-16-5f33ff60b3889-300x275.webp": {
      "size": 25404,
      "sha256": "1886652c26b7f5ce4d70a6b085525fa6282114ee0d25f2a2e07dbfcd3a1023e3"
    },
    "2026/06/1_WFS-1954-16-5f33ff60b3889-768x705.webp": {
      "size": 197904,
      "sha256": "b00cfe2e0130ed2cf51c6403682489df0d99f4b3f2b0658737b1c05ad4cc9157"
    },
    "2026/06/1_WFS-1954-16-5f33ff60b3889.webp": {
      "size": 533800,
      "sha256": "428cc94c827b1ea2fde24846ac298907864e63db60ad335e46c3fff11a138a02"
    },
    "2026/06/1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw-150x150.webp": {
      "size": 5410,
      "sha256": "6f5257ddf2c2066aa9bf4c572979ddbeb3e208632fe9936b55696587af509f7a"
    },
    "2026/06/1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw-300x300.webp": {
      "size": 16102,
      "sha256": "5786ccb97e16cb775808f18829cb91cb25386e9aadc843f2b9fb96c669dcefef"
    },
    "2026/06/1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw-768x767.webp": {
      "size": 74286,
      "sha256": "5e7928733a499dfe62f3d0b989b16168b3d2b12b3091cafa6f35e6a27a3d2ce9"
    },
    "2026/06/1_eine-gruppe-von-kindern-bei-der-ausstellung-des-zeichenwettbewerbs-der-berliner-zeitung-sw.webp": {
      "size": 110684,
      "sha256": "2d38cb8dea8a31dae07a38584a9ae32493aed8298fe0f978eeb560c3b063c5cf"
    },
    "2026/06/1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585-1024x1024.webp": {
      "size": 98476,
      "sha256": "db8bd9337645b65895a88b09e3f43ab7ef9ad03c902edd9bf54aa7be7ecae008"
    },
    "2026/06/1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585-150x150.webp": {
      "size": 6212,
      "sha256": "f07df634ea2daa9ad47ac45d38244579d86beeeea06c2918d3260cf7187ee84f"
    },
    "2026/06/1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585-300x300.webp": {
      "size": 17884,
      "sha256": "6b0d5d6347c8e6b9ff9d647cd4b3a3d0b9b667070de8daf025b0991cf5f1b574"
    },
    "2026/06/1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585-768x768.webp": {
      "size": 68926,
      "sha256": "ef421a0ce53017c84f84f06cc6f4f1ca4ddf8abba0dcc480888520ca48491890"
    },
    "2026/06/1_kinder-und-ein-kuenstler-beim-modellieren-mit-ton-1974-sw-foto-kurt-schwarz-98585.webp": {
      "size": 127160,
      "sha256": "0fa71c655cf342913a0de2b6218063ba72f375b48045ceb887be9280c4e361a5"
    },
    "2026/06/kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-1024x971.webp": {
      "size": 196306,
      "sha256": "d53e5e01bd007c3cd798dbd16f94cb1d670fb52288faab2e2a5e397c1ac46222"
    },
    "2026/06/kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-150x150.webp": {
      "size": 8708,
      "sha256": "06590d363938384f9d5dd6ce4a288fcd0a1e9f68f2251f7f38ea8f3fb39923b1"
    },
    "2026/06/kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-300x284.webp": {
      "size": 25596,
      "sha256": "4fb175b7b54ac5ebeadda1b47be5ae7a3d2f53ce6178686f3df568929579986c"
    },
    "2026/06/kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf-768x728.webp": {
      "size": 125370,
      "sha256": "8ed0d54e5b604d1ef3cf0636399e3d9f3ea9e4036b35b7eb619c0a1939118ae3"
    },
    "2026/06/kinder-mit-erzieherin-spielen-im-sandkasten-auf-dem-spielplatz-vor-dem-gebaeude-des-wf.webp": {
      "size": 297764,
      "sha256": "f000d5f2559eb2184780c48a14e3f1b51588bc5f80202dd4c11950433aa21ed2"
    },
    "event-drop-storage/accepted/16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide-pdf-1024x576.jpg": {
      "size": 12145,
      "sha256": "71580d6f45ab5310ba5dfa2d6ae8fe8ccac4c9c746bf55d367f1974a721b6016"
    },
    "event-drop-storage/accepted/16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide-pdf-150x84.jpg": {
      "size": 923,
      "sha256": "9fe592a55e74fd0ef10e027f22d048a8e12833584f87d372d15378be843cb4d8"
    },
    "event-drop-storage/accepted/16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide-pdf-300x169.jpg": {
      "size": 2154,
      "sha256": "7ee0ce49230c1c5ffb9680e80bc47e6afffdcac8eb5b69f0e4c24c1cec7473e4"
    },
    "event-drop-storage/accepted/16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide-pdf.jpg": {
      "size": 73041,
      "sha256": "4b76c05d6c5b77aded330ab8b40663971c184b598dd6ff22e60d575d6eb3cc58"
    },
    "event-drop-storage/accepted/16-20260626-071807-Brand-Guidelines_Industriesalon-Schoneweide.pdf": {
      "size": 151014831,
      "sha256": "03ba508588a8a2c131abf5dbd058ec3839e3823ff46368a1680731aae5d1ff2c"
    },
    "event-drop-storage/accepted/projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f-1024x576.png": {
      "size": 476533,
      "sha256": "25da7a325687d898e229f6255ebcdbc472bddca9edbf5dcd9175bef57ae67294"
    },
    "event-drop-storage/accepted/projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f-150x150.png": {
      "size": 16777,
      "sha256": "d01b155a9ac4c6b5c8a005ebe64ca3fc5383b5c6064cb98ffafdbd6f0a27bf59"
    },
    "event-drop-storage/accepted/projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f-1536x864.png": {
      "size": 1036156,
      "sha256": "4bdff73e7b5d49e04b834b89f645bd0fe66097ea9000d470a81733209710648f"
    },
    "event-drop-storage/accepted/projekt__walk-of-fame-schoeneweide_vladimir_20260626_071703_c4b7da3f-768x432.png": {
      "size": 270359,
      "sha256": "9178dd19059dfedb9a216fbacf7202a5aebb170dbf960204246a5fa3a32654fe"
    }
  }
}
ISS_SYNC_JSON
, true, 512, JSON_THROW_ON_ERROR);

$normalize = static function ($value) use (&$normalize) {
    if (is_string($value)) {
        return str_replace(['http://192.168.2.31:8082', 'http://localhost:8082', 'https://staging.industriesalon.info'], 'SITE_URL', $value);
    }
    if (is_array($value)) {
        foreach ($value as &$item) { $item = $normalize($item); }
    }
    return $value;
};
$translate = static function ($value) use (&$translate) {
    if (is_string($value)) { return str_replace('{{SITE_URL}}', home_url(), $value); }
    if (is_array($value)) {
        foreach ($value as &$item) { $item = $translate($item); }
    }
    return $value;
};
$payload['records'] = $translate($payload['records']);
$documents = 0;
foreach ($payload['records'] as &$row) {
    if (isset($row['document'])) {
        ++$documents;
        // On staging, missing attachment rows are prerequisites created in the transaction.
        if (!in_array($mode, ['validate', 'verify'], true)) { continue; }
        $validated = iss_editorial_validate_document($row['document'], $row['format']);
        if (is_wp_error($validated)) { WP_CLI::error("Invalid document {$row['id']}: " . $validated->get_error_message()); }
        $row['document'] = $validated;
    }
}
unset($row);
if ($mode === 'validate') {
    WP_CLI::success("$documents source documents validate.");
    return;
}
if (home_url() !== $payload['target_url']) { WP_CLI::error('This migration is scoped to staging.'); }

global $wpdb;
$verify = static function () use ($payload): void {
    foreach ($payload['records'] as $row) {
        $id = $row['id'];
        $post = get_post($id, ARRAY_A);
        if (!$post) { throw new RuntimeException("Missing post $id"); }
        foreach ($row['post'] as $key => $value) {
            if ((string) $post[$key] !== (string) $value) { throw new RuntimeException("Post $id field $key differs"); }
        }
        foreach ($row['meta'] as $key => $values) {
            if (get_post_meta($id, $key, false) != $values) { throw new RuntimeException("Post $id meta $key differs"); }
        }
        if (isset($row['document'])) {
            if (iss_editorial_get_document($id, $row['format'], false) != $row['document']) { throw new RuntimeException("Document $id differs"); }
            if (iss_editorial_document_is_enabled($id, $row['format']) !== $row['enabled']) { throw new RuntimeException("Document $id enabled flag differs"); }
        }
    }
};
if ($mode === 'verify') {
    $verify();
    WP_CLI::success(count($payload['records']) . " records and $documents documents match the reviewed source.");
    return;
}

// Guard every touched post field and metadata key against concurrent target edits.
foreach ($payload['records'] as $row) {
    $id = $row['id'];
    $post = get_post($id, ARRAY_A);
    if ($row['new']) {
        if ($post) { WP_CLI::error("Expected unused ID $id; refusing to overwrite."); }
        $same_slug = get_posts(['post_type' => $row['post']['post_type'], 'post_status' => 'any', 'name' => $row['post']['post_name'], 'numberposts' => 1]);
        if ($same_slug) { WP_CLI::error("Slug collision for new post $id."); }
    } else {
        if (!$post || $post['post_type'] !== $row['identity']['type'] || $post['post_name'] !== $row['identity']['slug']) { WP_CLI::error("Identity mismatch for $id."); }
        foreach ($row['expected_fields'] as $key => $hash) {
            if (hash('sha256', $normalize($post[$key])) !== $hash) { WP_CLI::error("Target post $id changed: $key."); }
        }
    }
    foreach ($row['expected_meta'] as $key => $expected) {
        $values = array_column($wpdb->get_results($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $id, $key), ARRAY_A), 'meta_value');
        $hashes = array_map(static fn($value) => hash('sha256', json_encode($normalize(maybe_unserialize($value)))), $values);
        if ($hashes !== $expected) { WP_CLI::error("Target metadata changed: $id $key."); }
    }
    foreach (array_keys($row['terms'] ?? []) as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) { WP_CLI::error("Missing taxonomy $taxonomy."); }
    }
}
$uploads = wp_upload_dir()['basedir'];
foreach ($payload['media'] as $relative => $expected) {
    $file = $uploads . '/' . $relative;
    if (!is_file($file) || filesize($file) !== $expected['size'] || hash_file('sha256', $file) !== $expected['sha256']) { WP_CLI::error("Missing or changed media: $relative."); }
}
if ($mode === 'check') {
    WP_CLI::success(count($payload['records']) . " guarded records, $documents documents and " . count($payload['media']) . ' media files ready.');
    return;
}

$backup = ['records' => [], 'new_ids' => []];
foreach ($payload['records'] as $row) {
    if ($row['new']) { $backup['new_ids'][] = $row['id']; continue; }
    $terms = [];
    foreach (get_object_taxonomies(get_post_type($row['id'])) as $tax) { $terms[$tax] = wp_get_object_terms($row['id'], $tax, ['fields' => 'ids']); }
    $backup['records'][$row['id']] = ['post' => get_post($row['id'], ARRAY_A), 'meta' => get_post_meta($row['id']), 'terms' => $terms];
}
$backup_path = '/tmp/iss-editorial-sync-20260922-before.json';
$handle = fopen($backup_path, 'x');
if (!$handle) { WP_CLI::error('Targeted backup already exists or cannot be created.'); }
$encoded = wp_json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (fwrite($handle, $encoded) !== strlen($encoded)) { fclose($handle); WP_CLI::error('Incomplete backup; no content writes made.'); }
fclose($handle);
chmod($backup_path, 0600);

// Content transfer must not send notifications. No persistent mail setting changes.
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);
$wpdb->query('START TRANSACTION');
try {
    foreach ($payload['records'] as $row) {
        $id = $row['id'];
        if ($row['new']) {
            $insert = $row['post'];
            $insert['import_id'] = $id;
            $insert['post_author'] = 1;
            $result = wp_insert_post(wp_slash($insert), true);
            if (is_wp_error($result) || $result !== $id) { throw new RuntimeException("Could not create post $id"); }
        } elseif ($row['post']) {
            $result = wp_update_post(wp_slash(array_merge(['ID' => $id], $row['post'])), true);
            if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
        }
        foreach ($row['meta'] as $key => $values) {
            if (get_post_meta($id, $key, false) == $values) { continue; }
            delete_post_meta($id, $key);
            foreach ($values as $value) { add_post_meta($id, $key, wp_slash($value)); }
        }
        foreach ($row['terms'] ?? [] as $tax => $terms) {
            $term_ids = [];
            foreach ($terms as $term) {
                $existing = get_term_by('slug', $term['slug'], $tax);
                if (!$existing) {
                    $created = wp_insert_term($term['name'], $tax, ['slug' => $term['slug'], 'description' => $term['description']]);
                    if (is_wp_error($created)) { throw new RuntimeException($created->get_error_message()); }
                    $term_ids[] = (int) $created['term_id'];
                } else { $term_ids[] = (int) $existing->term_id; }
            }
            $result = wp_set_object_terms($id, $term_ids, $tax);
            if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
        }
        if (isset($row['document'])) {
            if (!iss_editorial_save_document($id, $row['format'], $row['document'], false)) { throw new RuntimeException("Document save failed: $id"); }
            iss_editorial_set_document_enabled($id, $row['format'], $row['enabled']);
            // Place projection is derived from the enabled document, not transferred table rows.
            if ($row['format'] === 'place' && $row['enabled']) {
                $result = iss_register_sync_editorial_place_projection($id, 'place', $row['document']);
                if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
            }
        }
    }
    $verify();
    $wpdb->query('COMMIT');
} catch (Throwable $error) {
    $wpdb->query('ROLLBACK');
    wp_cache_flush();
    WP_CLI::error('Rolled back content transaction: ' . $error->getMessage());
}
WP_CLI::success(count($payload['records']) . " records and $documents documents synchronized; backup $backup_path.");
