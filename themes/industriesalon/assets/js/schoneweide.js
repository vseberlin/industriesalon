(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var AtlasCore = Atlas.core || {};
  var AtlasConfig = Atlas.config || {};
  var AtlasLayout = Atlas.layout || {};
  var AtlasMap = Atlas.map || {};
  var AtlasProvider = Atlas.provider || {};
  var EMPTY = AtlasCore.EMPTY || '';
  var DEFAULT_ERA_LABEL = 'Alle Zeiten';
  var DEFAULT_STATUS_LABEL = 'Alle Situationen';
  var DEFAULT_USE_TYPE_LABEL = 'Alle Nutzungen';
  var CURRENT_STATUS_LABELS = {
    '': DEFAULT_STATUS_LABEL,
    in_use: 'In Nutzung',
    vacant: 'Leerstand',
    temporary_use: 'Zwischennutzung',
    in_transition: 'Im Umbau',
    planned: 'Projekt / Planung',
    for_sale: 'Verkauf / offen',
    unclear: 'Unklar'
  };
  var CURRENT_USE_TYPE_LABELS = {
    '': DEFAULT_USE_TYPE_LABEL,
    culture: 'Kultur',
    education: 'Bildung / Forschung',
    commercial: 'Gewerbe / Bueros',
    industrial: 'Produktion',
    residential: 'Wohnen',
    administration: 'Verwaltung',
    community: 'Gemeinwohl / Soziales',
    mixed: 'Mischnutzung'
  };
  var HISTORICAL_FUNCTION_LABELS = {
    '': 'Alle Funktionen',
    industrial: 'Industrie / Produktion',
    commercial: 'Gewerbe / Handel',
    culture: 'Kultur',
    education: 'Bildung / Forschung',
    community: 'Gemeinwohl / Soziales',
    residential: 'Wohnen',
    mixed: 'Mischnutzung',
    vacant: 'Leerstand',
    infrastructure: 'Infrastruktur',
    no_data: 'Keine Angabe'
  };
  var HISTORICAL_NO_DATA_KEY = 'no_data';
  var ERA_FILTER_CONTEXT_ONLY = true;
  var DEFAULT_ACTOR_LABEL = 'Alle Akteure';
  var UNKNOWN_EPOCH_SUMMARY =
    'Für diesen Ort liegen im gewählten Zeitfenster bisher keine gesicherten historischen Nachweise vor. Wenn Sie historische Dokumente, Fotos oder andere Objekte haben, freuen wir uns über Ihre Kontaktaufnahme.';
  var ATLAS_AREAS = {
    'Niederschöneweide': true,
    'Oberschöneweide': true,
    'Nalepastraße': true,
    Schöneweide: true,
    Wuhlheide: true
  };
  var MAP_BOUNDS = AtlasCore.MAP_BOUNDS;

  function text(value) {
    return typeof value === 'string' ? value.trim() : EMPTY;
  }

  function number(value) {
    var parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function compact(value, maxLength) {
    var normalized = text(value).replace(/\s+/g, ' ');

    if (!normalized || normalized.length <= maxLength) {
      return normalized;
    }

    return normalized.slice(0, maxLength - 1).trim() + '…';
  }

  function relativeUrl(value) {
    var normalized = text(value);

    if (!normalized) {
      return EMPTY;
    }

    try {
      var url = new URL(normalized, window.location.origin);
      return url.pathname + url.search + url.hash;
    } catch (error) {
      return normalized;
    }
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getStaticRelationMapUrl(config) {
    return AtlasProvider.getStaticRelationMapUrl(config);
  }

  function createElement(tagName, className, textValue) {
    var element = document.createElement(tagName);

    if (className) {
      element.className = className;
    }

    if (typeof textValue === 'string') {
      element.textContent = textValue;
    }

    return element;
  }

  function score(place) {
    if (Number.isFinite(Number(place.story_score))) {
      return Number(place.story_score);
    }

    var total = 0;
    total += Math.min(text(place.summary || place.excerpt).length, 260);
    total += Math.min(text(place.current_summary || place.current).length, 220);
    total += text(place.archive_image_url || place.featured_image_url) ? 160 : 0;
    total += text(place.current_status) === 'in_transition' ? 24 : 0;

    return total;
  }

  function normalizePlace(place) {
    return {
      post_id: Number.parseInt(place.post_id, 10) || 0,
      slug: text(place.slug),
      name: text(place.name),
      lat: number(place.lat),
      lng: number(place.lng),
      role: text(place.role).toUpperCase(),
      area: text(place.area),
      address: text(place.address),
      status: text(place.status),
      current_status: text(place.current_status).toLowerCase(),
      current_status_label: text(place.current_status_label),
      current_use_type: text(place.current_use_type).toLowerCase(),
      current_use_type_label: text(place.current_use_type_label),
      present_label: text(place.present_label),
      permalink: relativeUrl(place.permalink),
      featured_image_url: relativeUrl(place.featured_image_url),
      archive_image_url: relativeUrl(place.archive_image_url),
      color: text(place.color),
      summary: compact(place.summary || place.excerpt || place.current_summary || place.current, 220),
      secondary: compact(place.secondary || place.archive_summary || place.note_text, 160),
      era_id: text(place.era_id),
      era_label: text(place.era_label),
      era_slug: text(place.era_slug),
      era_name: text(place.era_name),
      era_source: text(place.era_source),
      explicit_era_slugs: Array.isArray(place.explicit_era_slugs)
        ? place.explicit_era_slugs.map(text).filter(Boolean)
        : [],
      explicit_era_names: Array.isArray(place.explicit_era_names)
        ? place.explicit_era_names.map(text).filter(Boolean)
        : [],
      industry_actor_keys: Array.isArray(place.industry_actor_keys)
        ? place.industry_actor_keys.map(function (key) {
            return text(key).toLowerCase();
          }).filter(Boolean)
        : [],
      industry_actor_labels: Array.isArray(place.industry_actor_labels)
        ? place.industry_actor_labels.map(text).filter(Boolean)
        : [],
      industry_actor_relations: Array.isArray(place.industry_actor_relations)
        ? place.industry_actor_relations.map(function (relation) {
            return {
              actor_key: text(relation.actor_key).toLowerCase(),
              actor_label: text(relation.actor_label),
              actor_name: text(relation.actor_name),
              actor_color: text(relation.actor_color),
              era_slug: text(relation.era_slug),
              relation_role: text(relation.relation_role),
              strength: text(relation.strength),
              source_confidence: text(relation.source_confidence),
              note: text(relation.note)
            };
          }).filter(function (relation) {
            return relation.actor_key;
          })
        : [],
      related_publications: Array.isArray(place.related_publications)
        ? place.related_publications.map(function (item) {
            return {
              id: Number.parseInt(item.id, 10) || 0,
              title: text(item.title),
              permalink: relativeUrl(item.permalink)
            };
          }).filter(function (item) {
            return item.id > 0 && item.permalink;
          })
        : [],
      epoch_summaries: Array.isArray(place.epoch_summaries)
        ? place.epoch_summaries.map(function (epoch) {
            return {
              era_slug: text(epoch.era_slug),
              function_key: text(epoch.function_key).toLowerCase(),
              phase_name: text(epoch.phase_name),
              summary: text(epoch.summary),
              start_year: Number.isFinite(Number.parseInt(epoch.start_year, 10)) ? Number.parseInt(epoch.start_year, 10) : null,
              end_year: Number.isFinite(Number.parseInt(epoch.end_year, 10)) ? Number.parseInt(epoch.end_year, 10) : null
            };
          }).filter(function (epoch) {
            return epoch.era_slug || epoch.function_key || epoch.phase_name || epoch.summary;
          })
        : [],
      archive_summary: compact(place.archive_summary, 160),
      current_summary: compact(place.current_summary, 160),
      note_text: compact(place.note_text, 120),
      storyScore: score(place)
    };
  }

  function normalizeStory(story) {
    return {
      id: Number.parseInt(story.id, 10) || 0,
      slug: text(story.slug),
      title: text(story.title),
      excerpt: compact(story.excerpt, 260),
      permalink: relativeUrl(story.permalink),
      featured_image_url: relativeUrl(story.featured_image_url),
      eraSlugs: Array.isArray(story.era_slugs) ? story.era_slugs.map(text).filter(Boolean) : [],
      eraLegacyIds: Array.isArray(story.era_legacy_ids) ? story.era_legacy_ids.map(text).filter(Boolean) : [],
      placeIds: Array.isArray(story.place_ids)
        ? story.place_ids.map(function (value) {
            return Number.parseInt(value, 10) || 0;
          }).filter(Boolean)
        : []
    };
  }

  function normalizeEra(era) {
    return {
      slug: text(era.slug),
      name: text(era.name),
      caption: text(era.caption),
      legacyId: text(era.legacy_id),
      legacyLabel: text(era.legacy_label),
      legacyShortLabel: text(era.legacy_short_label),
      legacyCaption: text(era.legacy_caption),
      placeCount: Number.parseInt(era.place_count, 10) || 0,
      storyCount: Number.parseInt(era.story_count, 10) || 0
    };
  }

  function normalizeActor(actor) {
    return {
      key: text(actor.key).toLowerCase(),
      label: text(actor.label),
      name: text(actor.name),
      color: text(actor.color),
      placeCount: Number.parseInt(actor.place_count, 10) || 0,
      eraCounts: actor.era_counts && typeof actor.era_counts === 'object' ? actor.era_counts : {}
    };
  }

  function isAtlasPlace(place) {
    if (place.post_id <= 0 || place.lat === null || place.lng === null || !place.permalink) {
      return false;
    }

    return ATLAS_AREAS[place.area] === true;
  }

  function currentStatusLabel(place) {
    if (text(place.current_status_label)) {
      return place.current_status_label;
    }

    return CURRENT_STATUS_LABELS[text(place.current_status).toLowerCase()] || 'Ort';
  }

  function currentUseTypeLabel(place) {
    if (text(place.current_use_type_label)) {
      return place.current_use_type_label;
    }

    return CURRENT_USE_TYPE_LABELS[text(place.current_use_type).toLowerCase()] || EMPTY;
  }

  function presentLabel(place) {
    if (text(place.present_label)) {
      return place.present_label;
    }

    var pieces = [];
    var status = currentStatusLabel(place);
    var type = currentUseTypeLabel(place);

    if (status && status !== 'Ort') {
      pieces.push(status);
    }

    if (type) {
      pieces.push(type);
    }

    return pieces.join(' · ') || 'Ort';
  }

  function historyLabel(place) {
    if (place.explicit_era_names.length) {
      return place.explicit_era_names.join(' · ');
    }

    return text(place.era_name) || text(place.era_label) || 'Zeitfenster offen';
  }

  function getMatchingEpochSummary(place, state) {
    if (!state.activeEra || !Array.isArray(place.epoch_summaries) || !place.epoch_summaries.length) {
      return null;
    }

    var eraCandidates = place.epoch_summaries.filter(function (epoch) {
      return text(epoch.era_slug) === state.era;
    });

    if (!eraCandidates.length) {
      return null;
    }

    if (state.currentUseType) {
      var exact = eraCandidates.find(function (epoch) {
        return text(epoch.function_key) === state.currentUseType && (text(epoch.summary) || text(epoch.phase_name));
      });
      if (exact) {
        return exact;
      }
    }

    var withSummary = eraCandidates.find(function (epoch) {
      return !!text(epoch.summary);
    });

    return withSummary || eraCandidates[0];
  }

  function epochRangeLabel(epoch) {
    if (!epoch || (!Number.isFinite(epoch.start_year) && !Number.isFinite(epoch.end_year))) {
      return EMPTY;
    }

    var start = Number.isFinite(epoch.start_year) ? String(epoch.start_year) : '?';
    var end = Number.isFinite(epoch.end_year) ? String(epoch.end_year) : 'heute';

    return start + '–' + end;
  }

  function popupKickerLabel(place, state, _epochContext) {
    if (state.activeEra) {
      if (state.currentUseType) {
        return useTypeFilterLabel(state, state.currentUseType);
      }

      return state.activeEra.name || state.activeEra.legacyLabel || historyLabel(place);
    }

    return presentLabel(place);
  }

  function popupTitleLabel(place, state, epochContext) {
    if (state.activeEra && isUnknownEpochFunction(place, state)) {
      return 'Historische Funktion unklar';
    }

    if (state.activeEra && epochContext && text(epochContext.phase_name)) {
      return text(epochContext.phase_name);
    }

    return text(place.name) || 'Ort';
  }

  function activeSummary(place, state) {
    if (state.activeEra) {
      if (isUnknownEpochFunction(place, state)) {
        return UNKNOWN_EPOCH_SUMMARY;
      }

      var epoch = getMatchingEpochSummary(place, state);
      if (epoch && text(epoch.summary)) {
        return text(epoch.summary);
      }

      return text(place.archive_summary) || text(place.summary);
    }

    if (state.currentStatus || state.currentUseType) {
      return text(place.current_summary) || text(place.summary);
    }

    return text(place.summary);
  }

  function toRadians(value) {
    return (value * Math.PI) / 180;
  }

  function distanceMeters(left, right) {
    if (
      !left
      || !right
      || !Number.isFinite(left.lat)
      || !Number.isFinite(left.lng)
      || !Number.isFinite(right.lat)
      || !Number.isFinite(right.lng)
    ) {
      return Number.POSITIVE_INFINITY;
    }

    var earthRadius = 6371000;
    var dLat = toRadians(right.lat - left.lat);
    var dLng = toRadians(right.lng - left.lng);
    var lat1 = toRadians(left.lat);
    var lat2 = toRadians(right.lat);
    var sinLat = Math.sin(dLat / 2);
    var sinLng = Math.sin(dLng / 2);
    var a = (sinLat * sinLat) + (Math.cos(lat1) * Math.cos(lat2) * sinLng * sinLng);
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return earthRadius * c;
  }

  function formatDistanceLabel(value) {
    if (!Number.isFinite(value)) {
      return EMPTY;
    }

    if (value < 1000) {
      return Math.round(value) + ' m entfernt';
    }

    return (Math.round((value / 1000) * 10) / 10).toFixed(1).replace('.', ',') + ' km entfernt';
  }

  function uniqueList(values) {
    var seen = {};
    var result = [];

    (Array.isArray(values) ? values : []).forEach(function (value) {
      var key = text(value).toLowerCase();
      if (!key || seen[key]) {
        return;
      }

      seen[key] = true;
      result.push(key);
    });

    return result;
  }

  function intersectList(left, right) {
    var rightSet = {};
    var result = [];

    uniqueList(right).forEach(function (value) {
      rightSet[value] = true;
    });

    uniqueList(left).forEach(function (value) {
      if (rightSet[value]) {
        result.push(value);
      }
    });

    return result;
  }

  function getEraSlugsForPlace(place) {
    var fromEpochs = Array.isArray(place.epoch_summaries)
      ? place.epoch_summaries.map(function (epoch) { return text(epoch.era_slug); }).filter(Boolean)
      : [];

    if (fromEpochs.length) {
      return uniqueList(fromEpochs);
    }

    if (Array.isArray(place.explicit_era_slugs) && place.explicit_era_slugs.length) {
      return uniqueList(place.explicit_era_slugs);
    }

    if (text(place.era_slug)) {
      return [text(place.era_slug)];
    }

    return [];
  }

  function getEpochForEra(place, eraSlug) {
    if (!eraSlug || !Array.isArray(place.epoch_summaries)) {
      return null;
    }

    var matches = place.epoch_summaries.filter(function (epoch) {
      return text(epoch.era_slug) === eraSlug;
    });

    if (!matches.length) {
      return null;
    }

    var withSummary = matches.find(function (epoch) {
      return text(epoch.summary) || text(epoch.phase_name);
    });

    return withSummary || matches[0];
  }

  function getEpochFunctionKeys(place, eraSlug) {
    if (!Array.isArray(place.epoch_summaries) || !place.epoch_summaries.length) {
      return [];
    }

    if (eraSlug) {
      var single = getEpochForEra(place, eraSlug);
      return single && text(single.function_key) ? [text(single.function_key)] : [];
    }

    return uniqueList(place.epoch_summaries.map(function (epoch) {
      return text(epoch.function_key);
    }).filter(Boolean));
  }

  function getPublicationIds(place) {
    if (!Array.isArray(place.related_publications) || !place.related_publications.length) {
      return [];
    }

    return uniqueList(place.related_publications.map(function (publication) {
      return String(Number.parseInt(publication.id, 10) || 0);
    }).filter(function (value) {
      return value !== '0';
    }));
  }

  function getAllActorKeys(place) {
    if (Array.isArray(place.industry_actor_relations) && place.industry_actor_relations.length) {
      return uniqueList(place.industry_actor_relations.map(function (relation) {
        return text(relation.actor_key);
      }).filter(Boolean));
    }

    return uniqueList(place.industry_actor_keys || []);
  }

  function mapEraNames(state, eraSlugs) {
    return eraSlugs.map(function (slug) {
      if (state.eraMap[slug]) {
        return state.eraMap[slug].name || state.eraMap[slug].legacyLabel || slug;
      }

      return slug;
    });
  }

  function buildSpatialRelations(selectedPlace, places, state) {
    var selectedActors = state.activeEra
      ? getActorKeysForEra(selectedPlace, state.era)
      : getAllActorKeys(selectedPlace);

    return places
      .filter(function (place) {
        return place.post_id !== selectedPlace.post_id;
      })
      .map(function (place) {
        var distance = distanceMeters(selectedPlace, place);
        if (!Number.isFinite(distance)) {
          return null;
        }

        var placeActors = state.activeEra
          ? getActorKeysForEra(place, state.era)
          : getAllActorKeys(place);
        var sharedActors = intersectList(selectedActors, placeActors);
        var sharedUseType = text(selectedPlace.current_use_type) !== EMPTY
          && text(selectedPlace.current_use_type) === text(place.current_use_type);
        var score = Math.max(0, 4200 - Math.min(distance, 4200)) / 480;

        if (sharedActors.length) {
          score += sharedActors.length * 1.7;
        }
        if (sharedUseType) {
          score += 0.6;
        }

        var parts = [formatDistanceLabel(distance)];
        if (sharedActors.length) {
          parts.push('Akteur: ' + sharedActors.map(function (key) {
            return actorFilterLabel(state, key);
          }).join(', '));
        }
        if (sharedUseType) {
          parts.push('ähnliche heutige Nutzung');
        }

        return {
          place: place,
          score: score,
          meta: parts.filter(Boolean).join(' · ')
        };
      })
      .filter(Boolean)
      .sort(function (left, right) {
        if (right.score !== left.score) {
          return right.score - left.score;
        }

        return text(left.place.name).localeCompare(text(right.place.name), 'de');
      })
      .slice(0, 4);
  }

  function buildTemporalRelations(selectedPlace, places, state) {
    var selectedEraSlugs = getEraSlugsForPlace(selectedPlace);
    var selectedActiveEpoch = state.activeEra ? getEpochForEra(selectedPlace, state.era) : null;
    var selectedFunctions = getEpochFunctionKeys(selectedPlace, state.activeEra ? state.era : EMPTY);

    return places
      .filter(function (place) {
        return place.post_id !== selectedPlace.post_id;
      })
      .map(function (place) {
        var placeEraSlugs = getEraSlugsForPlace(place);
        var sharedEras = intersectList(selectedEraSlugs, placeEraSlugs);
        var activeEpoch = state.activeEra ? getEpochForEra(place, state.era) : null;
        var placeFunctions = getEpochFunctionKeys(place, state.activeEra ? state.era : EMPTY);
        var sharedFunctions = intersectList(selectedFunctions, placeFunctions);
        var score = sharedEras.length * 1.2;

        if (state.activeEra && sharedEras.indexOf(state.era) === -1) {
          return null;
        }

        if (sharedFunctions.length) {
          score += sharedFunctions.length * 1.5;
        }

        if (selectedActiveEpoch && activeEpoch) {
          var selectedStart = Number.isFinite(selectedActiveEpoch.start_year) ? selectedActiveEpoch.start_year : null;
          var selectedEnd = Number.isFinite(selectedActiveEpoch.end_year) ? selectedActiveEpoch.end_year : 2026;
          var activeStart = Number.isFinite(activeEpoch.start_year) ? activeEpoch.start_year : null;
          var activeEnd = Number.isFinite(activeEpoch.end_year) ? activeEpoch.end_year : 2026;
          if (selectedStart !== null && activeStart !== null && activeStart <= selectedEnd && selectedStart <= activeEnd) {
            score += 1.1;
          }
        }

        if (score <= 0) {
          return null;
        }

        var parts = [];
        if (state.activeEra && activeEpoch) {
          var range = epochRangeLabel(activeEpoch);
          if (range) {
            parts.push(range);
          }
          if (text(activeEpoch.phase_name)) {
            parts.push(text(activeEpoch.phase_name));
          }
          if (sharedFunctions.length) {
            parts.push('Funktion: ' + useTypeFilterLabel(state, sharedFunctions[0]));
          }
        } else {
          if (sharedEras.length) {
            parts.push('Gemeinsame Epochen: ' + mapEraNames(state, sharedEras).slice(0, 3).join(', '));
          }
          if (sharedFunctions.length) {
            parts.push('Ähnliche Funktionen');
          }
        }

        return {
          place: place,
          score: score,
          meta: parts.filter(Boolean).join(' · ')
        };
      })
      .filter(Boolean)
      .sort(function (left, right) {
        if (right.score !== left.score) {
          return right.score - left.score;
        }

        return text(left.place.name).localeCompare(text(right.place.name), 'de');
      })
      .slice(0, 4);
  }

  function buildSocialRelations(selectedPlace, places, state) {
    var selectedPublications = getPublicationIds(selectedPlace);
    var selectedActors = state.activeEra
      ? getActorKeysForEra(selectedPlace, state.era)
      : getAllActorKeys(selectedPlace);
    var selectedFunctions = getEpochFunctionKeys(selectedPlace, state.activeEra ? state.era : EMPTY);

    return places
      .filter(function (place) {
        return place.post_id !== selectedPlace.post_id;
      })
      .map(function (place) {
        var publicationOverlap = intersectList(selectedPublications, getPublicationIds(place));
        var sharedActors = intersectList(selectedActors, state.activeEra ? getActorKeysForEra(place, state.era) : getAllActorKeys(place));
        var sharedFunctions = intersectList(selectedFunctions, getEpochFunctionKeys(place, state.activeEra ? state.era : EMPTY));
        var sharedUseType = text(selectedPlace.current_use_type) !== EMPTY
          && text(selectedPlace.current_use_type) === text(place.current_use_type);
        var score = 0;

        if (publicationOverlap.length) {
          score += publicationOverlap.length * 2.3;
        }
        if (sharedFunctions.length) {
          score += sharedFunctions.length * 1.5;
        }
        if (sharedActors.length) {
          score += sharedActors.length * 1.1;
        }
        if (sharedUseType) {
          score += 0.7;
        }

        if (score <= 0) {
          return null;
        }

        var parts = [];
        if (publicationOverlap.length) {
          parts.push('gemeinsame Publikation(en)');
        }
        if (sharedFunctions.length) {
          parts.push('Funktionsbezug: ' + useTypeFilterLabel(state, sharedFunctions[0]));
        }
        if (sharedActors.length) {
          parts.push('Akteurnetz: ' + actorFilterLabel(state, sharedActors[0]));
        }
        if (sharedUseType) {
          parts.push('ähnliche heutige Nutzung');
        }

        return {
          place: place,
          score: score,
          meta: parts.filter(Boolean).join(' · ')
        };
      })
      .filter(Boolean)
      .sort(function (left, right) {
        if (right.score !== left.score) {
          return right.score - left.score;
        }

        return text(left.place.name).localeCompare(text(right.place.name), 'de');
      })
      .slice(0, 4);
  }

  function renderRelationRail(container, state, title, description, rows, emptyText, selectedPlaceId) {
    function buildMiniMap(place) {
      var map = createElement('div', 'iss-atlas-relations__mini-map');
      var dot = createElement('span', 'iss-atlas-relations__mini-map-dot');
      var hasMap = text(state.relationMapUrl);
      var x;
      var y;

      if (!hasMap) {
        return null;
      }

      x = ((number(place.lng) - MAP_BOUNDS.minLng) / (MAP_BOUNDS.maxLng - MAP_BOUNDS.minLng)) * 100;
      y = ((MAP_BOUNDS.maxLat - number(place.lat)) / (MAP_BOUNDS.maxLat - MAP_BOUNDS.minLat)) * 100;

      if (!Number.isFinite(x) || !Number.isFinite(y)) {
        return null;
      }

      x = Math.max(0, Math.min(100, x));
      y = Math.max(0, Math.min(100, y));

      map.style.backgroundImage = 'url("' + state.relationMapUrl + '")';
      map.style.backgroundPosition = x.toFixed(2) + '% ' + y.toFixed(2) + '%';
      map.appendChild(dot);

      return map;
    }

    var rail = createElement('article', 'iss-atlas-relations__rail');
    var head = createElement('header', 'iss-atlas-relations__rail-head');
    var list = createElement('div', 'iss-atlas-relations__rail-list');

    head.appendChild(createElement('p', 'iss-atlas-relations__rail-kicker', title));
    head.appendChild(createElement('p', 'iss-atlas-relations__rail-description', description));
    rail.appendChild(head);

    if (!rows.length) {
      list.appendChild(createElement('p', 'iss-atlas-relations__empty', emptyText));
      rail.appendChild(list);
      container.appendChild(rail);
      return;
    }

    rows.forEach(function (row) {
      var item = createElement('article', 'iss-atlas-relations__item' + (row.place.post_id === selectedPlaceId ? ' is-active' : EMPTY));
      var selectButton = createElement('button', 'iss-atlas-relations__select', row.place.name || 'Ort');
      var meta = createElement('p', 'iss-atlas-relations__meta', row.meta || 'Bezug im Korridor');
      var footer = createElement('div', 'iss-atlas-relations__footer');
      var miniMap = buildMiniMap(row.place);

      if (miniMap) {
        item.appendChild(miniMap);
      }

      selectButton.type = 'button';
      selectButton.addEventListener('click', function () {
        state.selectedPostId = row.place.post_id;
        state.shouldPan = true;
        state.render();
      });

      item.appendChild(selectButton);
      item.appendChild(meta);

      if (row.place.permalink) {
        var link = createElement('a', 'iss-action-link iss-atlas-relations__link', 'Dossier');
        link.href = row.place.permalink;
        footer.appendChild(link);
      }

      if (footer.children.length) {
        item.appendChild(footer);
      }

      list.appendChild(item);
    });

    rail.appendChild(list);
    container.appendChild(rail);
  }

  function renderRelations(container, state, selectedPlace, relationPool) {
    if (!container) {
      return;
    }

    container.innerHTML = EMPTY;

    if (!selectedPlace) {
      container.appendChild(
        createElement('p', 'iss-atlas-relations__empty', 'Ort auswählen, um räumliche, zeitliche und soziale Beziehungen zu sehen.')
      );
      return;
    }

    var pool = relationPool.filter(function (place) {
      return place.post_id !== selectedPlace.post_id;
    });
    var spatial = buildSpatialRelations(selectedPlace, pool, state);
    var temporal = buildTemporalRelations(selectedPlace, pool, state);
    var social = buildSocialRelations(selectedPlace, pool, state);

    renderRelationRail(
      container,
      state,
      'Räumliche Beziehungen',
      'Nähe im Korridor, ergänzt um Akteursbezüge.',
      spatial,
      'Keine belastbaren räumlichen Nachbarschaften in der aktuellen Auswahl.',
      selectedPlace.post_id
    );
    renderRelationRail(
      container,
      state,
      'Zeitliche Beziehungen',
      'Gemeinsame Epochen, Phasen und Funktionslogiken.',
      temporal,
      'Keine zeitlichen Überschneidungen in der aktuellen Auswahl.',
      selectedPlace.post_id
    );
    renderRelationRail(
      container,
      state,
      'Soziale Beziehungen',
      'Publikationen, Nutzungsmuster und institutionelle Verbindungen.',
      social,
      'Keine sozialen bzw. funktionalen Verknüpfungen in der aktuellen Auswahl.',
      selectedPlace.post_id
    );
  }

  function sortPlaces(left, right) {
    if (right.storyScore !== left.storyScore) {
      return right.storyScore - left.storyScore;
    }

    return text(left.name).localeCompare(text(right.name), 'de');
  }

  function buildEraMap(eras) {
    var map = {};

    eras.forEach(function (era) {
      if (era.slug) {
        map[era.slug] = era;
      }
    });

    return map;
  }

  function buildActorMap(actors) {
    var map = {};

    actors.forEach(function (actor) {
      if (actor.key) {
        map[actor.key] = actor;
      }
    });

    return map;
  }

  function deriveFallbackActors(places) {
    var seen = {};
    var actors = [];

    places.forEach(function (place) {
      place.industry_actor_relations.forEach(function (relation) {
        var key = text(relation.actor_key).toLowerCase();
        if (!key || seen[key]) {
          return;
        }

        seen[key] = true;
        actors.push({
          key: key,
          label: text(relation.actor_label) || key.toUpperCase(),
          name: text(relation.actor_name) || text(relation.actor_label) || key.toUpperCase(),
          color: text(relation.actor_color),
          placeCount: 0,
          eraCounts: {}
        });
      });
    });

    return actors;
  }

  function deriveFallbackEras(places) {
    var seen = {};
    var eras = [];

    places.forEach(function (place) {
      var slug = text(place.era_slug);
      if (!slug || seen[slug]) {
        return;
      }

      seen[slug] = true;
      eras.push({
        slug: slug,
        name: text(place.era_name) || text(place.era_label) || slug,
        caption: EMPTY,
        legacyId: text(place.era_id),
        legacyLabel: text(place.era_label),
        legacyShortLabel: EMPTY,
        legacyCaption: EMPTY,
        placeCount: 0,
        storyCount: 0
      });
    });

    return eras;
  }

  function matchesEra(place, eraSlug) {
    if (!eraSlug) {
      return true;
    }

    if (text(place.era_slug) === eraSlug) {
      return true;
    }

    return place.explicit_era_slugs.indexOf(eraSlug) !== -1;
  }

  function getEraScopedPlaces(places, eraSlug) {
    if (!eraSlug) {
      return places.slice();
    }

    if (ERA_FILTER_CONTEXT_ONLY) {
      return places.slice();
    }

    return places.filter(function (place) {
      return matchesEra(place, eraSlug);
    });
  }

  function getEpochFunctionKeysForEra(place, eraSlug) {
    if (!eraSlug || !Array.isArray(place.epoch_summaries) || !place.epoch_summaries.length) {
      return [];
    }

    var keys = {};

    place.epoch_summaries.forEach(function (epoch) {
      if (text(epoch.era_slug) !== eraSlug) {
        return;
      }

      var key = text(epoch.function_key).toLowerCase();
      if (key) {
        keys[key] = true;
      }
    });

    return Object.keys(keys);
  }

  function getActorKeysForEra(place, eraSlug) {
    if (!Array.isArray(place.industry_actor_relations) || !place.industry_actor_relations.length) {
      return Array.isArray(place.industry_actor_keys) ? place.industry_actor_keys.slice() : [];
    }

    var keys = {};
    place.industry_actor_relations.forEach(function (relation) {
      var key = text(relation.actor_key).toLowerCase();
      if (!key) {
        return;
      }

      if (!eraSlug) {
        keys[key] = true;
        return;
      }

      var relationEra = text(relation.era_slug);
      if (relationEra === eraSlug) {
        keys[key] = true;
      }
    });

    return Object.keys(keys);
  }

  function placeMatchesActor(place, actorKey, eraSlug) {
    if (!actorKey) {
      return true;
    }

    return getActorKeysForEra(place, eraSlug).indexOf(actorKey) !== -1;
  }

  function actorFilterLabel(state, key) {
    if (!key) {
      return DEFAULT_ACTOR_LABEL;
    }

    var actor = state.actorMap[key];
    if (actor && actor.label) {
      return actor.label;
    }

    return key.toUpperCase();
  }

  function useTypeFilterLabel(state, key) {
    if (state.activeEra) {
      return HISTORICAL_FUNCTION_LABELS[key] || key;
    }

    return CURRENT_USE_TYPE_LABELS[key] || key;
  }

  function isUnknownEpochFunction(place, state) {
    if (!state || !state.activeEra || !state.era) {
      return false;
    }

    return getEpochFunctionKeysForEra(place, state.era).length === 0;
  }

  function filterPlaces(places, state) {
    var search = text(state.search).toLowerCase();

    return places.filter(function (place) {
      if (state.currentStatus && place.current_status !== state.currentStatus) {
        return false;
      }

      if (state.actorKey && !placeMatchesActor(place, state.actorKey, state.era)) {
        return false;
      }

      if (state.currentUseType) {
        if (state.activeEra) {
          var epochKeys = getEpochFunctionKeysForEra(place, state.era);
          if (state.currentUseType === HISTORICAL_NO_DATA_KEY) {
            if (epochKeys.length) {
              return false;
            }
          } else if (epochKeys.indexOf(state.currentUseType) === -1) {
            return false;
          }
        } else if (place.current_use_type !== state.currentUseType) {
          return false;
        }
      }

      if (!search) {
        return true;
      }

      var haystack = [
        place.name,
        place.address,
        place.area,
        place.current_status_label,
        place.current_use_type_label,
        place.summary,
        place.secondary
      ].join(' ').toLowerCase();

      return haystack.indexOf(search) !== -1;
    });
  }

  function getSelectedPlace(state, filteredPlaces) {
    if (!filteredPlaces.length || state.selectedPostId === -1) {
      return null;
    }

    var selected = filteredPlaces.find(function (place) {
      return place.post_id === state.selectedPostId;
    });

    return selected || filteredPlaces[0];
  }

  function buildFilterButton(options) {
    var button = createElement(
      'button',
      'iss-atlas-app__filter-button' +
        (options.className ? ' ' + options.className : EMPTY) +
        (options.active ? ' is-active' : EMPTY)
    );

    button.type = 'button';
    button.setAttribute('aria-pressed', options.active ? 'true' : 'false');
    if (options.attributes && typeof options.attributes === 'object') {
      Object.keys(options.attributes).forEach(function (key) {
        if (options.attributes[key] !== undefined && options.attributes[key] !== null) {
          button.setAttribute(key, String(options.attributes[key]));
        }
      });
    }
    button.appendChild(createElement('span', 'iss-atlas-app__filter-text', options.label));

    if (typeof options.count === 'number') {
      button.appendChild(
        createElement('span', 'iss-atlas-app__filter-count', String(options.count))
      );
    }

    button.addEventListener('click', options.onClick);

    return button;
  }

  function renderEraFilters(container, state) {
    var eraCounts = {};
    var eras = state.eras.length ? state.eras : deriveFallbackEras(state.places);

    if (ERA_FILTER_CONTEXT_ONLY) {
      eras.forEach(function (era) {
        eraCounts[era.slug] = state.places.length;
      });
    } else {
      state.places.forEach(function (place) {
        eras.forEach(function (era) {
          if (matchesEra(place, era.slug)) {
            eraCounts[era.slug] = (eraCounts[era.slug] || 0) + 1;
          }
        });
      });
    }

    container.innerHTML = EMPTY;
    container.appendChild(buildFilterButton({
      label: DEFAULT_ERA_LABEL,
      count: state.places.length,
      active: state.era === EMPTY,
      className: 'iss-atlas-app__filter-button--era',
      attributes: {
        'data-era-slug': 'all'
      },
      onClick: function () {
        state.era = EMPTY;
        state.currentUseType = EMPTY;
        state.shouldPan = false;
        state.render();
      }
    }));

    eras.forEach(function (era) {
      var label = era.name || era.legacyLabel || era.slug;

      container.appendChild(buildFilterButton({
        label: label,
        count: eraCounts[era.slug] || 0,
        active: state.era === era.slug,
        className: 'iss-atlas-app__filter-button--era',
        attributes: {
          'data-era-slug': era.slug
        },
        onClick: function () {
          state.era = era.slug;
          state.currentUseType = EMPTY;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function renderCurrentStatusFilters(container, state, eraScopedPlaces) {
    var counts = { '': eraScopedPlaces.length };
    var statuses = [EMPTY].concat(Object.keys(CURRENT_STATUS_LABELS).filter(Boolean));

    eraScopedPlaces.forEach(function (place) {
      counts[place.current_status] = (counts[place.current_status] || 0) + 1;
    });

    container.innerHTML = EMPTY;

    statuses.forEach(function (status) {
      container.appendChild(buildFilterButton({
        label: CURRENT_STATUS_LABELS[status] || status,
        count: counts[status] || 0,
        active: state.currentStatus === status,
        onClick: function () {
          state.currentStatus = status;
          if (status && state.currentUseType) {
            var useTypeStillExists = eraScopedPlaces.some(function (place) {
              return place.current_status === status && place.current_use_type === state.currentUseType;
            });
            if (!useTypeStillExists) {
              state.currentUseType = EMPTY;
            }
          }
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function renderActorFilters(container, state, eraScopedPlaces) {
    var counts = { '': eraScopedPlaces.length };
    var actors = state.actors.length ? state.actors : deriveFallbackActors(state.places);
    var activeKeys = [];

    eraScopedPlaces.forEach(function (place) {
      getActorKeysForEra(place, state.era).forEach(function (key) {
        counts[key] = (counts[key] || 0) + 1;
      });
    });

    actors.forEach(function (actor) {
      if (actor.key && counts[actor.key]) {
        activeKeys.push(actor.key);
      }
    });

    if (state.actorKey && activeKeys.indexOf(state.actorKey) === -1) {
      state.actorKey = EMPTY;
    }

    container.innerHTML = EMPTY;
    container.appendChild(buildFilterButton({
      label: DEFAULT_ACTOR_LABEL,
      count: counts[''] || 0,
      active: state.actorKey === EMPTY,
      className: 'iss-atlas-app__filter-button--actor',
      attributes: {
        'data-actor-key': 'all'
      },
      onClick: function () {
        state.actorKey = EMPTY;
        state.selectedPostId = 0;
        state.shouldPan = false;
        state.render();
      }
    }));

    activeKeys.forEach(function (key) {
      container.appendChild(buildFilterButton({
        label: actorFilterLabel(state, key),
        count: counts[key] || 0,
        active: state.actorKey === key,
        className: 'iss-atlas-app__filter-button--actor',
        attributes: {
          'data-actor-key': key
        },
        onClick: function () {
          state.actorKey = key;
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function renderCurrentUseTypeFilters(container, state, statusScopedPlaces) {
    var counts = { '': statusScopedPlaces.length };
    var types = state.activeEra
      ? Object.keys(HISTORICAL_FUNCTION_LABELS).filter(Boolean)
      : Object.keys(CURRENT_USE_TYPE_LABELS).filter(Boolean);
    var activeTypes = [];

    statusScopedPlaces.forEach(function (place) {
      if (state.activeEra) {
        var epochKeys = getEpochFunctionKeysForEra(place, state.era);
        if (!epochKeys.length) {
          counts[HISTORICAL_NO_DATA_KEY] = (counts[HISTORICAL_NO_DATA_KEY] || 0) + 1;
          return;
        }

        epochKeys.forEach(function (key) {
          counts[key] = (counts[key] || 0) + 1;
        });
        return;
      }

      if (place.current_use_type) {
        counts[place.current_use_type] = (counts[place.current_use_type] || 0) + 1;
      }
    });

    types.forEach(function (type) {
      if (counts[type]) {
        activeTypes.push(type);
      }
    });

    container.innerHTML = EMPTY;
    container.parentElement.hidden = activeTypes.length === 0;

    if (!activeTypes.length) {
      state.currentUseType = EMPTY;
      return;
    }

    if (state.currentUseType && state.currentUseType !== HISTORICAL_NO_DATA_KEY && activeTypes.indexOf(state.currentUseType) === -1) {
      state.currentUseType = EMPTY;
    }

    if (state.currentUseType === HISTORICAL_NO_DATA_KEY && !counts[HISTORICAL_NO_DATA_KEY]) {
      state.currentUseType = EMPTY;
    }

    [EMPTY].concat(activeTypes).forEach(function (type) {
      container.appendChild(buildFilterButton({
        label: useTypeFilterLabel(state, type),
        count: counts[type] || 0,
        active: state.currentUseType === type,
        onClick: function () {
          state.currentUseType = type;
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        }
      }));
    });
  }

  function buildScopeLabel(state) {
    var pieces = [];

    if (state.activeEra) {
      pieces.push(state.activeEra.name || state.activeEra.legacyLabel || DEFAULT_ERA_LABEL);
    }

    if (state.currentStatus) {
      pieces.push(CURRENT_STATUS_LABELS[state.currentStatus] || state.currentStatus);
    }

    if (state.currentUseType) {
      pieces.push(useTypeFilterLabel(state, state.currentUseType));
    }

    if (state.actorKey) {
      pieces.push(actorFilterLabel(state, state.actorKey));
    }

    return pieces.join(' · ');
  }

  function renderCount(container, filteredPlaces, state) {
    if (!filteredPlaces.length) {
      container.textContent = 'Keine Orte in dieser Auswahl.';
      return;
    }

    var label = buildScopeLabel(state);
    container.textContent = label
      ? String(filteredPlaces.length) + ' Orte in ' + label
      : String(filteredPlaces.length) + ' Orte in der aktuellen Auswahl';
  }

  function renderSummary(container, filteredPlaces, selectedPlace, state) {
    container.innerHTML = EMPTY;

    if (!filteredPlaces.length) {
      return;
    }

    var headline = DEFAULT_ERA_LABEL;
    if (selectedPlace && text(selectedPlace.area)) {
      headline = selectedPlace.area;
    } else if (state.activeEra) {
      headline = state.activeEra.name || state.activeEra.legacyLabel || DEFAULT_ERA_LABEL;
    } else if (state.currentStatus) {
      headline = CURRENT_STATUS_LABELS[state.currentStatus] || DEFAULT_STATUS_LABEL;
    }

    container.appendChild(createElement('strong', EMPTY, headline));
    container.appendChild(
      createElement('span', EMPTY, String(filteredPlaces.length) + ' Orte in der aktuellen Auswahl')
    );
  }

  function setMapStatus(container, message) {
    container.innerHTML = EMPTY;

    if (!message) {
      container.classList.add('is-hidden');
      return;
    }

    container.classList.remove('is-hidden');
    container.appendChild(createElement('p', 'iss-atlas-loading', message));
  }

  function createMarkerIcon(place, active, state) {
    var statusClass = text(place.current_status)
      ? ' is-status-' + text(place.current_status).replace(/[^a-z0-9_-]+/g, '-')
      : EMPTY;
    var actorClass = EMPTY;
    var unknownFunctionClass = EMPTY;
    var highlightUnknowns = state && state.activeEra &&
      (state.currentUseType === HISTORICAL_NO_DATA_KEY || state.currentUseType === EMPTY);

    if (state && state.actorKey && placeMatchesActor(place, state.actorKey, state.era)) {
      actorClass = ' is-actor-focus is-actor-' + state.actorKey.replace(/[^a-z0-9_-]+/g, '-');
    }

    if (highlightUnknowns && isUnknownEpochFunction(place, state)) {
      unknownFunctionClass = ' is-function-unknown';
    }

    return window.L.divIcon({
      className: 'iss-atlas-marker' + statusClass + actorClass + unknownFunctionClass + (active ? ' is-active' : EMPTY),
      html:
        '<span class="iss-atlas-marker__dot"></span>' +
        '<span class="iss-atlas-marker__label">' +
        escapeHtml(compact(place.name, 42)) +
        '</span>',
      iconSize: [16, 16],
      iconAnchor: [8, 8]
    });
  }

  function createLeafletState(container, config) {
    return AtlasMap.createLeafletState(container, config);
  }

  function renderMap(state, filteredPlaces, selectedPlace) {
    AtlasMap.renderMarkers(state.leaflet, filteredPlaces, selectedPlace, {
      createMarkerIcon: function (place, active) {
        return createMarkerIcon(place, active, state);
      },
      onSelect: function (place) {
          state.selectedPostId = place.post_id;
          state.shouldPan = true;
          state.render();
      },
      shouldPan: state.shouldPan
    });
    state.shouldPan = false;
  }

  function buildPlaceMediaFigure(place, className, fallbackLabel) {
    var imageUrl = text(place.archive_image_url) || text(place.featured_image_url);
    var figure = createElement(
      'figure',
      className + (imageUrl ? EMPTY : ' is-fallback')
    );

    if (imageUrl) {
      var image = document.createElement('img');
      image.src = imageUrl;
      image.alt = place.name || EMPTY;
      image.decoding = 'async';
      figure.appendChild(image);
      return figure;
    }

    figure.style.background =
      text(place.color) ||
      'var(--iss-atlas-fallback-gradient, linear-gradient(145deg, rgba(56, 52, 48, 0.94), rgba(139, 127, 118, 0.82)))';
    figure.appendChild(createElement('span', 'iss-atlas-fallback__label', fallbackLabel));
    figure.appendChild(createElement('strong', 'iss-atlas-fallback__title', compact(place.name, 54)));

    return figure;
  }

  function appendFactItem(container, label, value, accentClass) {
    if (!text(value)) {
      return;
    }

    var item = createElement(
      'p',
      'iss-atlas-popup-card__fact' + (accentClass ? ' ' + accentClass : EMPTY)
    );
    item.appendChild(createElement('span', 'iss-atlas-popup-card__fact-label', label));
    item.appendChild(createElement('span', 'iss-atlas-popup-card__fact-value', value));
    container.appendChild(item);
  }

  function buildStoryMediaFigure(story, className, fallbackLabel) {
    var figure = createElement(
      'figure',
      className + (text(story.featured_image_url) ? EMPTY : ' is-fallback')
    );

    if (text(story.featured_image_url)) {
      var image = document.createElement('img');
      image.src = story.featured_image_url;
      image.alt = story.title || EMPTY;
      image.decoding = 'async';
      figure.appendChild(image);
      return figure;
    }

    figure.style.background =
      'var(--iss-atlas-fallback-gradient, linear-gradient(145deg, rgba(56, 52, 48, 0.94), rgba(139, 127, 118, 0.82)))';
    figure.appendChild(createElement('span', 'iss-atlas-fallback__label', fallbackLabel));
    figure.appendChild(createElement('strong', 'iss-atlas-fallback__title', compact(story.title, 54)));

    return figure;
  }

  function renderPopup(container, place, state) {
    container.innerHTML = EMPTY;
    container.classList.toggle('is-empty', !place);

    if (!place) {
      return;
    }

    var card = createElement('article', 'iss-card iss-card--flat iss-atlas-popup-card');
    var close = createElement('button', 'iss-atlas-popup-card__close', '×');
    var body = createElement('div', 'iss-card__body');
    var footer = createElement('div', 'iss-card__footer');
    var facts = createElement('div', 'iss-atlas-popup-card__facts');
    var primaryFacts = createElement('div', 'iss-atlas-popup-card__fact-group');
    var secondaryFacts = createElement('div', 'iss-atlas-popup-card__fact-group');
    var summaryText = activeSummary(place, state);
    var epochContext = getMatchingEpochSummary(place, state);

    close.type = 'button';
    close.setAttribute('aria-label', 'Details schließen');
    close.addEventListener('click', function () {
      container.dispatchEvent(new CustomEvent('iss-close-selection', { bubbles: true }));
    });

    card.appendChild(close);
    card.appendChild(
      buildPlaceMediaFigure(place, 'iss-card__media iss-atlas-popup-card__media', 'Ort')
    );

    body.appendChild(
      createElement('p', 'iss-card__kicker iss-kicker iss-kicker--compact', popupKickerLabel(place, state, epochContext))
    );
    body.appendChild(createElement('h3', 'iss-card__title', popupTitleLabel(place, state, epochContext)));

    var metaEraLabel = state.activeEra
      ? (state.activeEra.name || state.activeEra.legacyLabel || EMPTY)
      : (place.era_name || place.era_label);

    if (place.address || metaEraLabel) {
      body.appendChild(
        createElement(
          'p',
          'iss-atlas-popup-card__meta',
          [metaEraLabel, place.address].filter(Boolean).join(' · ')
        )
      );
    }

    if (state.activeEra) {
      var eraName = state.activeEra ? (state.activeEra.name || state.activeEra.legacyLabel || EMPTY) : EMPTY;
      appendFactItem(primaryFacts, 'Zeitfenster: ', eraName, 'is-emphasis');

      if (epochContext && text(epochContext.function_key)) {
        appendFactItem(primaryFacts, 'Funktion: ', useTypeFilterLabel(state, text(epochContext.function_key)));
      } else if (state.currentUseType) {
        appendFactItem(primaryFacts, 'Funktion: ', useTypeFilterLabel(state, state.currentUseType));
      }

      if (epochContext && text(epochContext.phase_name)) {
        appendFactItem(secondaryFacts, 'Phase: ', text(epochContext.phase_name));
      }

      if (epochContext) {
        appendFactItem(secondaryFacts, 'Zeitraum: ', epochRangeLabel(epochContext));
      }

      appendFactItem(secondaryFacts, 'Heute (zum Vergleich): ', presentLabel(place));
    } else {
      appendFactItem(primaryFacts, 'Historische Epochen: ', historyLabel(place));
      appendFactItem(primaryFacts, 'Heute: ', currentStatusLabel(place), 'is-emphasis');
      appendFactItem(primaryFacts, 'Nutzung: ', currentUseTypeLabel(place));
    }

    if (place.has_tour_usage) {
      appendFactItem(secondaryFacts, 'Führung: ', 'Im Rundgang enthalten');
    }

    if (primaryFacts.children.length) {
      facts.appendChild(primaryFacts);
    }

    if (secondaryFacts.children.length) {
      facts.appendChild(secondaryFacts);
    }

    if (facts.children.length) {
      body.appendChild(facts);
    }

    if (summaryText) {
      var summary = createElement('div', 'iss-atlas-popup-card__summary');
      var summaryLabel = 'Kurznotiz';
      if (state.activeEra && isUnknownEpochFunction(place, state)) {
        summaryLabel = 'Hinweis';
      } else if (state.activeEra && state.currentUseType) {
        summaryLabel = useTypeFilterLabel(state, state.currentUseType) + ' im Zeitfenster';
      } else if (state.activeEra) {
        summaryLabel = 'Einordnung zum gewählten Zeitfenster';
      } else if (state.currentStatus || state.currentUseType) {
        summaryLabel = 'Einordnung zur heutigen Situation';
      }
      summary.appendChild(createElement('p', 'iss-atlas-popup-card__summary-label', summaryLabel));
      summary.appendChild(createElement('p', 'iss-atlas-popup-card__summary-text', summaryText));
      body.appendChild(summary);
    }

    if (place.note_text) {
      body.appendChild(createElement('p', 'iss-atlas-popup-card__note', place.note_text));
    }

    if (place.permalink) {
      var link = createElement('a', 'iss-action-link', 'Zum Ort');
      link.href = place.permalink;
      footer.appendChild(link);
    }

    if (Array.isArray(place.related_publications) && place.related_publications.length) {
      var publication = place.related_publications[0];
      var prefersKwoPublication = text(place.name).toLowerCase().indexOf('kwo') !== -1
        || getActorKeysForEra(place, state.era).indexOf('kwo') !== -1;
      if (prefersKwoPublication) {
        var kwoPublication = place.related_publications.find(function (item) {
          return /kwo|kabelwerk/i.test(text(item.title));
        });
        if (kwoPublication) {
          publication = kwoPublication;
        }
      }
      if (publication && publication.permalink) {
        var publicationLink = createElement('a', 'iss-action-link', 'Zur Publikation');
        publicationLink.href = publication.permalink;
        publicationLink.title = publication.title || 'Publikation';
        footer.appendChild(publicationLink);
      }
    }

    if (footer.children.length) {
      body.appendChild(footer);
    }

    card.appendChild(body);
    container.appendChild(card);
  }

  function getSelectedStories(state) {
    if (!state.stories.length) {
      return [];
    }

    if (!state.era) {
      return state.stories.slice();
    }

    return state.stories.filter(function (story) {
      return story.eraSlugs.indexOf(state.era) !== -1;
    });
  }

  function pickStoryPlaces(source) {
    var chosen = [];
    var seen = {};
    var eraBuckets = {};

    source.forEach(function (place) {
      if (!place.era_slug) {
        return;
      }

      if (!eraBuckets[place.era_slug]) {
        eraBuckets[place.era_slug] = [];
      }

      eraBuckets[place.era_slug].push(place);
    });

    Object.keys(eraBuckets).forEach(function (slug) {
      var match = eraBuckets[slug].sort(sortPlaces)[0];

      if (match && !seen[match.post_id]) {
        chosen.push(match);
        seen[match.post_id] = true;
      }
    });

    source
      .slice()
      .sort(sortPlaces)
      .forEach(function (place) {
        if (chosen.length >= 6 || seen[place.post_id]) {
          return;
        }

        chosen.push(place);
        seen[place.post_id] = true;
      });

    return chosen.slice(0, 6);
  }

  function renderStoryIntro(container, state, stories, filteredPlaces) {
    container.innerHTML = EMPTY;

    if (!state.activeEra && !stories.length) {
      return;
    }

    var title = state.activeEra
      ? state.activeEra.name || state.activeEra.legacyLabel
      : 'Atlas';
    var body = EMPTY;

    if (stories.length && stories[0].excerpt) {
      body = stories[0].excerpt;
    } else if (state.activeEra && state.activeEra.caption) {
      body = state.activeEra.caption;
    } else if (filteredPlaces.length) {
      body = 'Die aktuelle Auswahl zeigt Orte, die zum gewählten Zeitfenster und zur Suche passen.';
    }

    var panel = createElement('div', 'iss-atlas-app__story-note');
    panel.appendChild(createElement('p', 'iss-atlas-app__story-note-kicker', state.activeEra ? 'Zeitfenster' : 'Atlas'));
    panel.appendChild(createElement('h3', 'iss-atlas-app__story-note-title', title));

    if (body) {
      panel.appendChild(createElement('p', 'iss-atlas-app__story-note-text', body));
    }

    container.appendChild(panel);
  }

  function renderStoryCards(container, stories, state) {
    stories.forEach(function (story) {
      var card = createElement('article', 'iss-card iss-card--flat iss-atlas-story-card');
      var body = createElement('div', 'iss-card__body');
      var footer = createElement('div', 'iss-card__footer');
      var kicker = state.activeEra
        ? state.activeEra.name || state.activeEra.legacyLabel
        : (story.eraSlugs[0] && state.eraMap[story.eraSlugs[0]]
            ? state.eraMap[story.eraSlugs[0]].name
            : 'Atlas');

      card.appendChild(buildStoryMediaFigure(story, 'iss-card__media', kicker));
      body.appendChild(createElement('p', 'iss-card__kicker iss-kicker iss-kicker--compact', kicker));
      body.appendChild(createElement('h3', 'iss-card__title', story.title || 'Geschichte'));

      if (story.excerpt) {
        body.appendChild(createElement('p', 'iss-card__text', story.excerpt));
      }

      if (story.permalink) {
        var link = createElement('a', 'iss-action-link', 'Geschichte lesen');
        link.href = story.permalink;
        footer.appendChild(link);
      }

      body.appendChild(footer);
      card.appendChild(body);
      container.appendChild(card);
    });
  }

  function renderFallbackPlaceCards(container, places) {
    pickStoryPlaces(places).forEach(function (place) {
      var card = createElement('article', 'iss-card iss-card--flat iss-atlas-story-card');
      var body = createElement('div', 'iss-card__body');
      var footer = createElement('div', 'iss-card__footer');
      var kicker = text(place.era_name) || text(place.era_label) || 'Schöneweide';

      card.appendChild(buildPlaceMediaFigure(place, 'iss-card__media', kicker));
      body.appendChild(createElement('p', 'iss-card__kicker iss-kicker iss-kicker--compact', kicker));
      body.appendChild(createElement('h3', 'iss-card__title', place.name || 'Ort'));

      if (place.summary) {
        body.appendChild(createElement('p', 'iss-card__text', place.summary));
      }

      if (place.permalink) {
        var link = createElement('a', 'iss-action-link', 'Dossier lesen');
        link.href = place.permalink;
        footer.appendChild(link);
      }

      body.appendChild(footer);
      card.appendChild(body);
      container.appendChild(card);
    });
  }

  function renderStories(container, state, filteredPlaces) {
    var stories = getSelectedStories(state);

    container.innerHTML = EMPTY;

    if (stories.length) {
      renderStoryCards(container, stories, state);
      return;
    }

    if (!filteredPlaces.length) {
      container.appendChild(
        createElement('p', 'iss-atlas-loading', 'Keine Geschichten in dieser Auswahl.')
      );
      return;
    }

    renderFallbackPlaceCards(container, filteredPlaces);
  }

  function render(elements, state) {
    if (!elements.statusFilters && state.currentStatus) {
      state.currentStatus = EMPTY;
    }

    var eraScopedPlaces = getEraScopedPlaces(state.places, state.era).sort(sortPlaces);
    var statusScopedPlaces = state.currentStatus
      ? eraScopedPlaces.filter(function (place) {
          return place.current_status === state.currentStatus;
        })
      : eraScopedPlaces.slice();
    var filteredPlaces = filterPlaces(eraScopedPlaces, state).sort(sortPlaces);
    var selectedPlace = getSelectedPlace(state, filteredPlaces);
    var selectedStories = getSelectedStories(state);

    state.activeEra = state.era ? (state.eraMap[state.era] || null) : null;
    if (state.root) {
      if (state.era) {
        state.root.setAttribute('data-active-era', state.era);
      } else {
        state.root.removeAttribute('data-active-era');
      }
      if (state.actorKey) {
        state.root.setAttribute('data-active-actor', state.actorKey);
      } else {
        state.root.removeAttribute('data-active-actor');
      }
    }

    if (selectedPlace) {
      state.selectedPostId = selectedPlace.post_id;
    }

    renderEraFilters(elements.eraFilters, state);
    if (elements.actorFilters) {
      renderActorFilters(elements.actorFilters, state, eraScopedPlaces);
    }
    if (elements.actorLabel) {
      elements.actorLabel.textContent = 'Industrieakteure';
    }
    if (elements.useTypeLabel) {
      elements.useTypeLabel.textContent = state.activeEra ? 'Funktion im Zeitfenster' : 'Nutzung heute';
    }
    if (elements.statusFilters) {
      renderCurrentStatusFilters(elements.statusFilters, state, eraScopedPlaces);
    } else {
      state.currentStatus = EMPTY;
    }
    renderCurrentUseTypeFilters(elements.useTypeFilters, state, statusScopedPlaces);
    renderStoryIntro(elements.storyIntro, state, selectedStories, filteredPlaces);
    renderCount(elements.count, filteredPlaces, state);
    renderRelations(elements.relations, state, selectedPlace, eraScopedPlaces);
    renderSummary(elements.summary, filteredPlaces, selectedPlace, state);
    renderMap(state, filteredPlaces, selectedPlace);
    renderPopup(elements.popup, selectedPlace, state);
    renderStories(elements.stories, state, filteredPlaces);
    setMapStatus(elements.mapStatus, EMPTY);
  }

  function renderError(elements, message) {
    elements.eraFilters.innerHTML = EMPTY;
    if (elements.statusFilters) {
      elements.statusFilters.innerHTML = EMPTY;
    }
    elements.useTypeFilters.innerHTML = EMPTY;
    if (elements.actorFilters) {
      elements.actorFilters.innerHTML = EMPTY;
    }
    elements.summary.innerHTML = EMPTY;
    elements.storyIntro.innerHTML = EMPTY;
    if (elements.relations) {
      elements.relations.innerHTML = EMPTY;
    }
    elements.popup.innerHTML = EMPTY;
    elements.popup.classList.add('is-empty');
    elements.stories.innerHTML = EMPTY;
    elements.count.textContent = message;
    elements.stories.appendChild(createElement('p', 'iss-atlas-loading', message));
    setMapStatus(elements.mapStatus, message);
  }

  function collectElements(root) {
    return {
      eraFilters: root.querySelector('[data-iss-schoneweide-era-filters]'),
      actorFilters: root.querySelector('[data-iss-schoneweide-actor-filters]'),
      actorLabel: root.querySelector('[data-iss-schoneweide-actor-label]'),
      statusFilters: root.querySelector('[data-iss-schoneweide-status-filters]'),
      useTypeFilters: root.querySelector('[data-iss-schoneweide-use-type-filters]'),
      useTypeLabel: root.querySelector('[data-iss-schoneweide-use-type-label]'),
      mapSurface: root.querySelector('.iss-atlas-app__map-surface'),
      mapCanvas: root.querySelector('[data-iss-schoneweide-map]'),
      mapStatus: root.querySelector('[data-iss-schoneweide-map-status]'),
      summary: root.querySelector('[data-iss-schoneweide-summary]'),
      relations: root.querySelector('[data-iss-schoneweide-relations]'),
      storyIntro: root.querySelector('[data-iss-schoneweide-story-intro]'),
      popup: root.querySelector('[data-iss-schoneweide-popup]'),
      stories: root.querySelector('[data-iss-schoneweide-stories]'),
      count: root.querySelector('[data-iss-schoneweide-count]'),
      search: root.querySelector('[data-iss-schoneweide-search]'),
      reset: root.querySelector('[data-iss-schoneweide-reset]')
    };
  }

  function bindMapSizeSync(map, surface) {
    AtlasLayout.bindMapSizeSync(map, surface);
  }

  function getAtlasPayload(config) {
    return AtlasConfig.getAtlasPayload(config);
  }

  function reportInitError(root, message, detail) {
    AtlasConfig.reportInitError(root, message, detail);
  }

  function readAtlasConfig(root) {
    return AtlasConfig.readAtlasConfig(root);
  }

  function collectRoots() {
    return AtlasConfig.collectRoots();
  }

  function initRoot(root) {
    if (!root || root.getAttribute('data-iss-schoneweide-atlas-ready') === 'true') {
      return;
    }

    root.setAttribute('data-iss-schoneweide-atlas-ready', 'booting');

    var config = readAtlasConfig(root);
    var elements = collectElements(root);
    var requiredKeys = [
      'eraFilters',
      'actorFilters',
      'useTypeFilters',
      'mapSurface',
      'mapCanvas',
      'mapStatus',
      'summary',
      'storyIntro',
      'popup',
      'stories',
      'count',
      'search',
      'reset'
    ];
    var missingKeys = requiredKeys.filter(function (key) {
      return !elements[key];
    });

    if (missingKeys.length) {
      root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
      reportInitError(root, 'Missing required atlas hook(s).', missingKeys);
      return;
    }

    if (!text(config.placesUrl)) {
      root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
      reportInitError(root, 'Missing atlas placesUrl config.');
      return;
    }

    if (!window.L) {
      root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
      renderError(elements, 'Die Kartenbibliothek konnte nicht geladen werden.');
      reportInitError(root, 'Leaflet was not available during atlas init.');
      return;
    }

    getAtlasPayload(config)
      .then(function (results) {
        var placePayload = Array.isArray(results[0]) ? results[0] : [];
        var contextPayload = results[1] && typeof results[1] === 'object' ? results[1] : {};
        var overlayPayload = results[2] && typeof results[2] === 'object' ? results[2] : { type: 'FeatureCollection', features: [] };
        var places = placePayload
          .map(normalizePlace)
          .filter(isAtlasPlace)
          .sort(sortPlaces);
        var eras = Array.isArray(contextPayload.eras)
          ? contextPayload.eras.map(normalizeEra).filter(function (era) { return era.slug; })
          : [];
        var stories = Array.isArray(contextPayload.stories)
          ? contextPayload.stories.map(normalizeStory).filter(function (story) { return story.id > 0; })
          : [];
        var actors = Array.isArray(contextPayload.actors)
          ? contextPayload.actors.map(normalizeActor).filter(function (actor) { return actor.key; })
          : [];

        if (!places.length) {
          root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
          renderError(elements, 'Keine Atlas-Orte verfügbar.');
          reportInitError(root, 'Atlas payload returned no usable places.');
          return;
        }

        var leafletState = createLeafletState(elements.mapCanvas, config);

        if (!leafletState) {
          root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
          renderError(elements, 'Die Karte konnte nicht initialisiert werden.');
          reportInitError(root, 'Leaflet map could not be initialized.');
          return;
        }

        AtlasMap.addOverlayLayer(leafletState, overlayPayload);

        var state = {
          root: root,
          leaflet: leafletState,
          relationMapUrl: getStaticRelationMapUrl(config),
          places: places,
          eras: eras,
          eraMap: buildEraMap(eras.length ? eras : deriveFallbackEras(places)),
          actors: actors.length ? actors : deriveFallbackActors(places),
          actorMap: buildActorMap(actors.length ? actors : deriveFallbackActors(places)),
          stories: stories,
          era: EMPTY,
          activeEra: null,
          actorKey: EMPTY,
          currentStatus: EMPTY,
          currentUseType: EMPTY,
          search: EMPTY,
          selectedPostId: places[0].post_id,
          shouldPan: false,
          render: function () {
            render(elements, state);
          }
        };

        elements.search.addEventListener('input', function (event) {
          state.search = event.target.value || EMPTY;
          state.selectedPostId = 0;
          state.shouldPan = false;
          state.render();
        });

        elements.reset.addEventListener('click', function () {
          state.era = EMPTY;
          state.actorKey = EMPTY;
          state.currentStatus = EMPTY;
          state.currentUseType = EMPTY;
          state.search = EMPTY;
          state.selectedPostId = 0;
          state.shouldPan = false;
          elements.search.value = EMPTY;
          state.render();
        });

        elements.popup.addEventListener('iss-close-selection', function () {
          state.selectedPostId = -1;
          state.shouldPan = false;
          state.render();
        });

        bindMapSizeSync(state.leaflet.map, elements.mapSurface);
        state.render();
        root.setAttribute('data-iss-schoneweide-atlas-ready', 'true');
      })
      .catch(function (error) {
        root.setAttribute('data-iss-schoneweide-atlas-ready', 'error');
        renderError(elements, 'Der Atlas konnte nicht geladen werden.');
        reportInitError(root, 'Atlas payload could not be loaded.', error);
      });
  }

  function init() {
    collectRoots().forEach(initRoot);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
