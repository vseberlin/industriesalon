<?php
/**
 * Repair missing media dependencies in unchanged canonical editor documents.
 * Staging only: wp eval-file FILE check|apply|verify --use-include
 * First transfer/verify ops/uploads/2026-09-22-editorial-media-repair.manifest.
 * Backup: /home/vladimir/server-actions/thumbnails-20260922/before.sql.gz.
 * Mount its cli/ directory at /tmp for the exclusive insertion receipt.
 * Rollback requires review: remove only the seven newly inserted attachment
 * records through WordPress APIs without deleting pre-existing upload files.
 * Eight newly transferred files are identified by the paired manifest.
 * No existing posts, documents, options or attachment records are updated.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$mode = $args[0] ?? 'check';
if (home_url() !== 'https://staging.industriesalon.info' || !in_array($mode, ['check', 'apply', 'verify'], true)) {
    WP_CLI::error('Staging only; use check, apply or verify.');
}
$payload = json_decode(<<<'ISS_MEDIA_REPAIR'
{
  "records": {
    "26558": {
      "owners": [
        24988
      ],
      "post": {
        "post_title": "Bild2_Kraftwerk",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "bild2_kraftwerk",
        "post_type": "attachment",
        "post_parent": 17974,
        "menu_order": 0,
        "post_mime_type": "image/jpeg",
        "post_date": "2026-06-15 17:10:49",
        "post_date_gmt": "2026-06-15 15:10:49"
      },
      "meta": {
        "_wp_attached_file": "2026/05/Bild2_Kraftwerk-1.jpg",
        "_wp_attachment_metadata": {
          "width": 800,
          "height": 578,
          "file": "2026/05/Bild2_Kraftwerk-1.jpg",
          "filesize": 223410,
          "sizes": {
            "medium": {
              "file": "Bild2_Kraftwerk-1-300x217.jpg",
              "width": 300,
              "height": 217,
              "mime-type": "image/jpeg",
              "filesize": 22067
            },
            "thumbnail": {
              "file": "Bild2_Kraftwerk-1-150x150.jpg",
              "width": 150,
              "height": 150,
              "mime-type": "image/jpeg",
              "filesize": 14699
            },
            "medium_large": {
              "file": "Bild2_Kraftwerk-1-768x555.jpg",
              "width": 768,
              "height": 555,
              "mime-type": "image/jpeg",
              "filesize": 79303
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
            "orientation": "1",
            "keywords": [],
            "alt": ""
          }
        }
      },
      "files": [
        "2026/05/Bild2_Kraftwerk-1.jpg",
        "2026/05/Bild2_Kraftwerk-1-300x217.jpg",
        "2026/05/Bild2_Kraftwerk-1-150x150.jpg",
        "2026/05/Bild2_Kraftwerk-1-768x555.jpg"
      ]
    },
    "26428": {
      "owners": [
        24988
      ],
      "post": {
        "post_title": "image2",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "image2",
        "post_type": "attachment",
        "post_parent": 25720,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-14 12:04:29",
        "post_date_gmt": "2026-06-14 10:04:29"
      },
      "meta": {
        "_wp_attached_file": "2026/05/image2.webp",
        "_wp_attachment_metadata": {
          "width": 830,
          "height": 912,
          "file": "2026/05/image2.webp",
          "filesize": 54410,
          "sizes": {
            "medium": {
              "file": "image2-273x300.webp",
              "width": 273,
              "height": 300,
              "mime-type": "image/webp",
              "filesize": 10962
            },
            "thumbnail": {
              "file": "image2-150x150.webp",
              "width": 150,
              "height": 150,
              "mime-type": "image/webp",
              "filesize": 3840
            },
            "medium_large": {
              "file": "image2-768x844.webp",
              "width": 768,
              "height": 844,
              "mime-type": "image/webp",
              "filesize": 61546
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
      },
      "files": [
        "2026/05/image2.webp",
        "2026/05/image2-273x300.webp",
        "2026/05/image2-150x150.webp",
        "2026/05/image2-768x844.webp"
      ]
    },
    "26052": {
      "owners": [
        13301
      ],
      "post": {
        "post_title": "2021-03-28-Sven-Bock-Fahrradtour-19",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "2021-03-28-sven-bock-fahrradtour-19",
        "post_type": "attachment",
        "post_parent": 13301,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-04 15:51:15",
        "post_date_gmt": "2026-06-04 13:51:15"
      },
      "meta": {
        "_wp_attached_file": "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-scaled.webp",
        "_wp_attachment_metadata": {
          "width": 2560,
          "height": 1707,
          "file": "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-scaled.webp",
          "filesize": 612170,
          "sizes": {
            "medium": {
              "file": "2021-03-28-Sven-Bock-Fahrradtour-19-300x200.webp",
              "width": 300,
              "height": 200,
              "mime-type": "image/webp",
              "filesize": 17368
            },
            "large": {
              "file": "2021-03-28-Sven-Bock-Fahrradtour-19-1024x683.webp",
              "width": 1024,
              "height": 683,
              "mime-type": "image/webp",
              "filesize": 135220
            },
            "thumbnail": {
              "file": "2021-03-28-Sven-Bock-Fahrradtour-19-150x150.webp",
              "width": 150,
              "height": 150,
              "mime-type": "image/webp",
              "filesize": 7696
            },
            "medium_large": {
              "file": "2021-03-28-Sven-Bock-Fahrradtour-19-768x512.webp",
              "width": 768,
              "height": 512,
              "mime-type": "image/webp",
              "filesize": 80574
            },
            "1536x1536": {
              "file": "2021-03-28-Sven-Bock-Fahrradtour-19-1536x1024.webp",
              "width": 1536,
              "height": 1024,
              "mime-type": "image/webp",
              "filesize": 263588
            },
            "2048x2048": {
              "file": "2021-03-28-Sven-Bock-Fahrradtour-19-2048x1366.webp",
              "width": 2048,
              "height": 1366,
              "mime-type": "image/webp",
              "filesize": 431510
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
          "original_image": "2021-03-28-Sven-Bock-Fahrradtour-19.webp"
        }
      },
      "files": [
        "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-scaled.webp",
        "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-300x200.webp",
        "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-1024x683.webp",
        "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-150x150.webp",
        "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-768x512.webp",
        "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-1536x1024.webp",
        "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-2048x1366.webp",
        "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19.webp"
      ]
    },
    "26054": {
      "owners": [
        13301
      ],
      "post": {
        "post_title": "GlasVitrine2B",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "glasvitrine2b",
        "post_type": "attachment",
        "post_parent": 13301,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-04 15:55:01",
        "post_date_gmt": "2026-06-04 13:55:01"
      },
      "meta": {
        "_wp_attached_file": "2026/06/GlasVitrine2B-scaled.webp",
        "_wp_attachment_metadata": {
          "width": 2560,
          "height": 1920,
          "file": "2026/06/GlasVitrine2B-scaled.webp",
          "filesize": 409150,
          "sizes": {
            "medium": {
              "file": "GlasVitrine2B-300x225.webp",
              "width": 300,
              "height": 225,
              "mime-type": "image/webp",
              "filesize": 18742
            },
            "large": {
              "file": "GlasVitrine2B-1024x768.webp",
              "width": 1024,
              "height": 768,
              "mime-type": "image/webp",
              "filesize": 111566
            },
            "thumbnail": {
              "file": "GlasVitrine2B-150x150.webp",
              "width": 150,
              "height": 150,
              "mime-type": "image/webp",
              "filesize": 8102
            },
            "medium_large": {
              "file": "GlasVitrine2B-768x576.webp",
              "width": 768,
              "height": 576,
              "mime-type": "image/webp",
              "filesize": 73410
            },
            "1536x1536": {
              "file": "GlasVitrine2B-1536x1152.webp",
              "width": 1536,
              "height": 1152,
              "mime-type": "image/webp",
              "filesize": 201962
            },
            "2048x2048": {
              "file": "GlasVitrine2B-2048x1536.webp",
              "width": 2048,
              "height": 1536,
              "mime-type": "image/webp",
              "filesize": 307660
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
          "original_image": "GlasVitrine2B.webp"
        }
      },
      "files": [
        "2026/06/GlasVitrine2B-scaled.webp",
        "2026/06/GlasVitrine2B-300x225.webp",
        "2026/06/GlasVitrine2B-1024x768.webp",
        "2026/06/GlasVitrine2B-150x150.webp",
        "2026/06/GlasVitrine2B-768x576.webp",
        "2026/06/GlasVitrine2B-1536x1152.webp",
        "2026/06/GlasVitrine2B-2048x1536.webp",
        "2026/06/GlasVitrine2B.webp"
      ]
    },
    "26030": {
      "owners": [
        13301
      ],
      "post": {
        "post_title": "kwo-broshure",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "kwo-broshure",
        "post_type": "attachment",
        "post_parent": 12606,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-04 09:13:15",
        "post_date_gmt": "2026-06-04 07:13:15"
      },
      "meta": {
        "_wp_attached_file": "2026/06/kwo-broshure.webp",
        "_wp_attachment_metadata": {
          "width": 1054,
          "height": 1492,
          "file": "2026/06/kwo-broshure.webp",
          "filesize": 154534,
          "sizes": {
            "medium": {
              "file": "kwo-broshure-212x300.webp",
              "width": 212,
              "height": 300,
              "mime-type": "image/webp",
              "filesize": 11856
            },
            "large": {
              "file": "kwo-broshure-723x1024.webp",
              "width": 723,
              "height": 1024,
              "mime-type": "image/webp",
              "filesize": 82420
            },
            "thumbnail": {
              "file": "kwo-broshure-150x150.webp",
              "width": 150,
              "height": 150,
              "mime-type": "image/webp",
              "filesize": 6032
            },
            "medium_large": {
              "file": "kwo-broshure-768x1087.webp",
              "width": 768,
              "height": 1087,
              "mime-type": "image/webp",
              "filesize": 90740
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
      },
      "files": [
        "2026/06/kwo-broshure.webp",
        "2026/06/kwo-broshure-212x300.webp",
        "2026/06/kwo-broshure-723x1024.webp",
        "2026/06/kwo-broshure-150x150.webp",
        "2026/06/kwo-broshure-768x1087.webp"
      ]
    },
    "26055": {
      "owners": [
        13301
      ],
      "post": {
        "post_title": "gisi",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "gisi",
        "post_type": "attachment",
        "post_parent": 13301,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-04 16:26:09",
        "post_date_gmt": "2026-06-04 14:26:09"
      },
      "meta": {
        "_wp_attached_file": "2026/06/gisi.webp",
        "_wp_attachment_metadata": {
          "width": 1673,
          "height": 940,
          "file": "2026/06/gisi.webp",
          "filesize": 328568,
          "sizes": {
            "medium": {
              "file": "gisi-300x169.webp",
              "width": 300,
              "height": 169,
              "mime-type": "image/webp",
              "filesize": 14756
            },
            "large": {
              "file": "gisi-1024x575.webp",
              "width": 1024,
              "height": 575,
              "mime-type": "image/webp",
              "filesize": 98822
            },
            "thumbnail": {
              "file": "gisi-150x150.webp",
              "width": 150,
              "height": 150,
              "mime-type": "image/webp",
              "filesize": 7658
            },
            "medium_large": {
              "file": "gisi-768x432.webp",
              "width": 768,
              "height": 432,
              "mime-type": "image/webp",
              "filesize": 63460
            },
            "1536x1536": {
              "file": "gisi-1536x863.webp",
              "width": 1536,
              "height": 863,
              "mime-type": "image/webp",
              "filesize": 174360
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
      },
      "files": [
        "2026/06/gisi.webp",
        "2026/06/gisi-300x169.webp",
        "2026/06/gisi-1024x575.webp",
        "2026/06/gisi-150x150.webp",
        "2026/06/gisi-768x432.webp",
        "2026/06/gisi-1536x863.webp"
      ]
    },
    "26057": {
      "owners": [
        13301
      ],
      "post": {
        "post_title": "2020-12-02-Archive-WF-In-Industriesalon-19",
        "post_content": "",
        "post_excerpt": "",
        "post_status": "inherit",
        "post_name": "2020-12-02-archive-wf-in-industriesalon-19",
        "post_type": "attachment",
        "post_parent": 13301,
        "menu_order": 0,
        "post_mime_type": "image/webp",
        "post_date": "2026-06-04 16:29:32",
        "post_date_gmt": "2026-06-04 14:29:32"
      },
      "meta": {
        "_wp_attached_file": "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-scaled.webp",
        "_wp_attachment_metadata": {
          "width": 2560,
          "height": 1920,
          "file": "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-scaled.webp",
          "filesize": 104652,
          "sizes": {
            "medium": {
              "file": "2020-12-02-Archive-WF-In-Industriesalon-19-300x225.webp",
              "width": 300,
              "height": 225,
              "mime-type": "image/webp",
              "filesize": 7294
            },
            "large": {
              "file": "2020-12-02-Archive-WF-In-Industriesalon-19-1024x768.webp",
              "width": 1024,
              "height": 768,
              "mime-type": "image/webp",
              "filesize": 32910
            },
            "thumbnail": {
              "file": "2020-12-02-Archive-WF-In-Industriesalon-19-150x150.webp",
              "width": 150,
              "height": 150,
              "mime-type": "image/webp",
              "filesize": 3714
            },
            "medium_large": {
              "file": "2020-12-02-Archive-WF-In-Industriesalon-19-768x576.webp",
              "width": 768,
              "height": 576,
              "mime-type": "image/webp",
              "filesize": 22750
            },
            "1536x1536": {
              "file": "2020-12-02-Archive-WF-In-Industriesalon-19-1536x1152.webp",
              "width": 1536,
              "height": 1152,
              "mime-type": "image/webp",
              "filesize": 54294
            },
            "2048x2048": {
              "file": "2020-12-02-Archive-WF-In-Industriesalon-19-2048x1536.webp",
              "width": 2048,
              "height": 1536,
              "mime-type": "image/webp",
              "filesize": 78772
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
          "original_image": "2020-12-02-Archive-WF-In-Industriesalon-19.webp"
        }
      },
      "files": [
        "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-scaled.webp",
        "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-300x225.webp",
        "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-1024x768.webp",
        "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-150x150.webp",
        "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-768x576.webp",
        "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-1536x1152.webp",
        "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-2048x1536.webp",
        "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19.webp"
      ]
    }
  },
  "files": {
    "2026/05/Bild2_Kraftwerk-1-150x150.jpg": {
      "size": 14699,
      "sha256": "8ce63947ba354292aa4ef3ac5a516d23a90966688eb395a2404bed1f37bf7046"
    },
    "2026/05/Bild2_Kraftwerk-1-300x217.jpg": {
      "size": 22067,
      "sha256": "2417427907245fd1b3f20398ad26cda4be5ddf94e614277ef5db5fe65c0679d6"
    },
    "2026/05/Bild2_Kraftwerk-1-768x555.jpg": {
      "size": 79303,
      "sha256": "65e5ce1e01bcb6672337a134fc6ffb9028ea11bc80a5e3cdc35dba85c364d510"
    },
    "2026/05/Bild2_Kraftwerk-1.jpg": {
      "size": 223410,
      "sha256": "a8bc3659fbfcf26da25247360ce54846a0afe8d49919923b4e628eb17a32abfb"
    },
    "2026/05/image2-150x150.webp": {
      "size": 3840,
      "sha256": "e0fa5223adabf8cd884ee7a4b4510b40987226fb93d0a32a7d765b2e0ef30e70"
    },
    "2026/05/image2-273x300.webp": {
      "size": 10962,
      "sha256": "23c9da57a0c06876a5e67ea162dea5f80a41f07e177cc83a257df255dfba0dfb"
    },
    "2026/05/image2-768x844.webp": {
      "size": 61546,
      "sha256": "407ea9afde167186e4a6d204a80570e14a015abf02a69d097204f8b849e57e2c"
    },
    "2026/05/image2.webp": {
      "size": 54410,
      "sha256": "8fd420dde5534897097f052d83ea606a02b4ffb3ff9e207220cabbd15cf2390f"
    },
    "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-1024x768.webp": {
      "size": 32910,
      "sha256": "b6bb01fd9e6d1092c6ee9422b91dea07a581de0382a4ef9d60a691cda3240d3f"
    },
    "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-150x150.webp": {
      "size": 3714,
      "sha256": "496b81aca19f01dafb1ceb90175f587d10bc630887c3a16ca62d46c83bc32c79"
    },
    "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-1536x1152.webp": {
      "size": 54294,
      "sha256": "1251a476d9de26d26a4681d5b02a6f55ce97b0914cd1f20a6ea9e3a79a9d04c4"
    },
    "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-2048x1536.webp": {
      "size": 78772,
      "sha256": "cc45c3a253d0a221693a463adc70feb3a23266ae36a46f3816e489eee05c0f67"
    },
    "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-300x225.webp": {
      "size": 7294,
      "sha256": "63fb237dc545a06eef07df657644d128a91fe9ee4a17e5e9b69c36d4b2b5e892"
    },
    "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-768x576.webp": {
      "size": 22750,
      "sha256": "00db1be83d38d653e751bc3bd4dc8cce781393a074ffe74325959e980753383d"
    },
    "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19-scaled.webp": {
      "size": 104652,
      "sha256": "d27655084579781b6d165c2d60897c07cb7ecf1ec296f53465bc55668897ba65"
    },
    "2026/06/2020-12-02-Archive-WF-In-Industriesalon-19.webp": {
      "size": 162580,
      "sha256": "3f1df52aae13605d1536af4460f0be2f62622d49d278f2eddb23e65674f79fb9"
    },
    "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-1024x683.webp": {
      "size": 135220,
      "sha256": "18c823fa5c1b67e14378c2b09233ac5f6e7beee77a4c50d9205bf0f616e43742"
    },
    "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-150x150.webp": {
      "size": 7696,
      "sha256": "613caab8e849c9ebecd8a54023afc215f9bf1d0c3cafbd237b20e47abb36f9d6"
    },
    "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-1536x1024.webp": {
      "size": 263588,
      "sha256": "37ff3920639e58e56a38430c7da1c8ca44676d6775767a214bfd353cfb2dda10"
    },
    "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-2048x1366.webp": {
      "size": 431510,
      "sha256": "937df9c065c1879382610531b4f1ffddc05548a44448d058c49f6368407e075b"
    },
    "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-300x200.webp": {
      "size": 17368,
      "sha256": "4e249abfb7536f76d1f1b3d75c4ad13a3e72e6f73b8bc623cba3e71e04c6c0e9"
    },
    "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-768x512.webp": {
      "size": 80574,
      "sha256": "5c70402dc24e723f865919691775b888fc834dfdcfb038fc5a7994ea861c995e"
    },
    "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19-scaled.webp": {
      "size": 612170,
      "sha256": "ae45565dccf1dae325ee8623a58a5539cc366d7afeea049c1f667cd6d9ca60bd"
    },
    "2026/06/2021-03-28-Sven-Bock-Fahrradtour-19.webp": {
      "size": 1360316,
      "sha256": "f2c04bbbe85c67896d71a03c7bb8ad31e239253ef46c1e4594b27af80adfd001"
    },
    "2026/06/GlasVitrine2B-1024x768.webp": {
      "size": 111566,
      "sha256": "6d904cdc0a903117f087f3aa59dca81b80b70446b7073a97325ac0797d120529"
    },
    "2026/06/GlasVitrine2B-150x150.webp": {
      "size": 8102,
      "sha256": "7d1a710c4ed8f14171f0f07ae7b9f240246331fd11f8b9c94d9000c3eb203485"
    },
    "2026/06/GlasVitrine2B-1536x1152.webp": {
      "size": 201962,
      "sha256": "1674f65478c48a09492c7a69be9db0e8c46cc615d689165f06a031d5a5085a42"
    },
    "2026/06/GlasVitrine2B-2048x1536.webp": {
      "size": 307660,
      "sha256": "597677e56e7701339f0a0012ce7f945d8c7ca63693dbe32ee0a9dd8d0c621e69"
    },
    "2026/06/GlasVitrine2B-300x225.webp": {
      "size": 18742,
      "sha256": "5f0f1f4ac8d8b44ff008c78aeeaada4530508eda09e63fa0a60ea15d261ad96b"
    },
    "2026/06/GlasVitrine2B-768x576.webp": {
      "size": 73410,
      "sha256": "ca29d8b3273715e7a34a088db5e36ff63a54b95460a84bbf639d9b26a01d709a"
    },
    "2026/06/GlasVitrine2B-scaled.webp": {
      "size": 409150,
      "sha256": "c67ac13a3b0d7da2d2fe7d3fd686bd83979dfb83b5ad483dc0390187b1d2b272"
    },
    "2026/06/GlasVitrine2B.webp": {
      "size": 596678,
      "sha256": "a350843f53d3aec055f9f137fdc4160b644652ac0c0c9e77d2517c52b025eb40"
    },
    "2026/06/gisi-1024x575.webp": {
      "size": 98822,
      "sha256": "5da8bf2673ec8e478b9df1488be41f2a3ad6f18dde9956e61818b9722af4bcbd"
    },
    "2026/06/gisi-150x150.webp": {
      "size": 7658,
      "sha256": "4b2b76f9795d62c8ded16360a0f8699acb618af683f606e293892dafc6384cdc"
    },
    "2026/06/gisi-1536x863.webp": {
      "size": 174360,
      "sha256": "4f5dcf88c9ed7dfea533c662fb263ce3b673886994d4cb0de623251032277132"
    },
    "2026/06/gisi-300x169.webp": {
      "size": 14756,
      "sha256": "d376c6e1e85c89d13a6b5e1d4638716563726cf4d2ca46d44e9338313e22702c"
    },
    "2026/06/gisi-768x432.webp": {
      "size": 63460,
      "sha256": "041499e5f352eb6d819fc352381f721fb426fea816e3908961ecfa069d3106b3"
    },
    "2026/06/gisi.webp": {
      "size": 328568,
      "sha256": "9abfda07ee763cfb143e986523676ed2b6eba53b760a64bed69e7c59390c5742"
    },
    "2026/06/kwo-broshure-150x150.webp": {
      "size": 6032,
      "sha256": "1e04624752941eafb684a19c159cdc99b9f8ad1cc609ed0862a319f6c07e79d3"
    },
    "2026/06/kwo-broshure-212x300.webp": {
      "size": 11856,
      "sha256": "c7a063d68f6bb21b68c4e5d2b22d0dd840183411dee0fc2315df691b7718e529"
    },
    "2026/06/kwo-broshure-723x1024.webp": {
      "size": 82420,
      "sha256": "f3f3518a0d87ab31c9c6ecd1b8a19b6df5557c7b06b369cb0500db852f393fcd"
    },
    "2026/06/kwo-broshure-768x1087.webp": {
      "size": 90740,
      "sha256": "6185d23736bbbd5f60c5eb5dfe117f51f5efd8210b7af363a62e6d371ad90e9d"
    },
    "2026/06/kwo-broshure.webp": {
      "size": 154534,
      "sha256": "31bd4bdfbab77340e62ca9b5e56cd476ccda2ed175151e2ed755c55314518d8c"
    }
  }
}
ISS_MEDIA_REPAIR
, true, 512, JSON_THROW_ON_ERROR);
$base = wp_get_upload_dir()['basedir'];
foreach ($payload['files'] as $file => $expected) {
    $path = $base . '/' . $file;
    if (!is_file($path) || filesize($path) !== $expected['size'] || hash_file('sha256', $path) !== $expected['sha256']) {
        WP_CLI::error('Required upload missing or changed: ' . $file);
    }
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-only diagnostics, never HTML output.
$verify = static function () use ($payload): void {
    foreach ($payload['records'] as $id => $record) {
        $post = get_post((int) $id, ARRAY_A);
        if (!$post) { throw new RuntimeException('Attachment missing: ' . $id); }
        foreach ($record['post'] as $key => $value) {
            if ((string) $post[$key] !== (string) $value) { throw new RuntimeException("Attachment field differs: $id $key"); }
        }
        foreach ($record['meta'] as $key => $value) {
            if (get_post_meta((int) $id, $key, true) !== $value) { throw new RuntimeException("Attachment metadata differs: $id $key"); }
        }
        if (!wp_get_attachment_image((int) $id, 'medium_large')) { throw new RuntimeException('Image cannot render: ' . $id); }
    }
};
if ($mode === 'verify') { $verify(); WP_CLI::success('Seven attachment records and all 43 required image files verified.'); return; }
foreach ($payload['records'] as $id => $record) {
    if (get_post((int) $id) || get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'name' => $record['post']['post_name']])) {
        WP_CLI::error('Attachment ID or slug already exists; refusing replacement: ' . $id);
    }
    if ($record['post']['post_parent'] && !get_post($record['post']['post_parent'])) { WP_CLI::error('Missing attachment parent: ' . $id); }
    foreach ($record['owners'] as $owner) { if (!get_post($owner)) { WP_CLI::error('Missing content owner: ' . $owner); } }
}
if ($mode === 'check') { WP_CLI::success('Seven unused attachment IDs and all 43 image files ready.'); return; }
$receipt = '/tmp/iss-media-repair-20260922-before.json';
$handle = fopen($receipt, 'x');
if (!$handle) { WP_CLI::error('Receipt exists or cannot be created; refusing replay.'); }
chmod($receipt, 0600);
$encoded = wp_json_encode(['new_attachment_ids' => array_keys($payload['records']), 'previous_records' => []], JSON_PRETTY_PRINT);
if (fwrite($handle, $encoded) !== strlen($encoded)) { fclose($handle); WP_CLI::error('Incomplete receipt.'); }
fclose($handle);
wp_set_current_user(1);
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);
global $wpdb;
$wpdb->query('START TRANSACTION'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transaction control, no data query to cache.
try {
    foreach ($payload['records'] as $id => $record) {
        $post = $record['post'];
        $post['import_id'] = (int) $id;
        $post['post_author'] = 1;
        $post['meta_input'] = $record['meta'];
        if (wp_insert_post(wp_slash($post), true) !== (int) $id) { throw new RuntimeException('Could not insert attachment: ' . $id); }
    }
    $verify();
    $wpdb->query('COMMIT'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transaction control.
} catch (Throwable $error) {
    $wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transaction control.
    wp_cache_flush();
    WP_CLI::error('Rolled back attachment repair: ' . $error->getMessage());
}
WP_CLI::success('Seven missing attachment records restored; existing content unchanged.');
