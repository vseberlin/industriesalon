(function () {
  var Atlas = window.issSchoneweideAtlas || {};
  var core = Atlas.core || {};
  var EMPTY = core.EMPTY || '';
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
  function text(value) {
    return typeof core.text === 'function' ? core.text(value) : (typeof value === 'string' ? value.trim() : EMPTY);
  }

  function number(value) {
    if (typeof core.number === 'function') {
      return core.number(value);
    }

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
    return typeof core.relativeUrl === 'function' ? core.relativeUrl(value) : text(value);
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

    return true;
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
    if (!filteredPlaces.length || state.selectedPostId <= 0) {
      return null;
    }

    return filteredPlaces.find(function (place) {
      return place.post_id === state.selectedPostId;
    }) || null;
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

  function epochRangeLabel(epoch) {
    if (!epoch || (!Number.isFinite(epoch.start_year) && !Number.isFinite(epoch.end_year))) {
      return EMPTY;
    }

    var start = Number.isFinite(epoch.start_year) ? String(epoch.start_year) : '?';
    var end = Number.isFinite(epoch.end_year) ? String(epoch.end_year) : 'heute';

    return start + '–' + end;
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
        var relationScore = Math.max(0, 4200 - Math.min(distance, 4200)) / 480;

        if (sharedActors.length) {
          relationScore += sharedActors.length * 1.7;
        }
        if (sharedUseType) {
          relationScore += 0.6;
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
          score: relationScore,
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
        var relationScore = sharedEras.length * 1.2;

        if (state.activeEra && sharedEras.indexOf(state.era) === -1) {
          return null;
        }

        if (sharedFunctions.length) {
          relationScore += sharedFunctions.length * 1.5;
        }

        if (selectedActiveEpoch && activeEpoch) {
          var selectedStart = Number.isFinite(selectedActiveEpoch.start_year) ? selectedActiveEpoch.start_year : null;
          var selectedEnd = Number.isFinite(selectedActiveEpoch.end_year) ? selectedActiveEpoch.end_year : 2026;
          var activeStart = Number.isFinite(activeEpoch.start_year) ? activeEpoch.start_year : null;
          var activeEnd = Number.isFinite(activeEpoch.end_year) ? activeEpoch.end_year : 2026;
          if (selectedStart !== null && activeStart !== null && activeStart <= selectedEnd && selectedStart <= activeEnd) {
            relationScore += 1.1;
          }
        }

        if (relationScore <= 0) {
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
          score: relationScore,
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
        var relationScore = 0;

        if (publicationOverlap.length) {
          relationScore += publicationOverlap.length * 2.3;
        }
        if (sharedFunctions.length) {
          relationScore += sharedFunctions.length * 1.5;
        }
        if (sharedActors.length) {
          relationScore += sharedActors.length * 1.1;
        }
        if (sharedUseType) {
          relationScore += 0.7;
        }

        if (relationScore <= 0) {
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
          score: relationScore,
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

  function createState(options) {
    var places = options.places || [];
    var eras = options.eras || [];
    var actors = options.actors || [];
    var fallbackActors = actors.length ? actors : deriveFallbackActors(places);
    var fallbackEras = eras.length ? eras : deriveFallbackEras(places);

    return {
      root: options.root,
      leaflet: options.leaflet,
      relationMapUrl: options.relationMapUrl || EMPTY,
      places: places,
      eras: eras,
      eraMap: buildEraMap(fallbackEras),
      actors: fallbackActors,
      actorMap: buildActorMap(fallbackActors),
      stories: options.stories || [],
      era: EMPTY,
      activeEra: null,
      actorKey: EMPTY,
      currentStatus: EMPTY,
      currentUseType: EMPTY,
      search: EMPTY,
      selectedPostId: 0,
      shouldPan: false,
      shouldResetView: false,
      render: typeof options.render === 'function' ? options.render : function () {}
    };
  }

  Atlas.store = {
    labels: {
      DEFAULT_ERA_LABEL: DEFAULT_ERA_LABEL,
      DEFAULT_STATUS_LABEL: DEFAULT_STATUS_LABEL,
      DEFAULT_USE_TYPE_LABEL: DEFAULT_USE_TYPE_LABEL,
      CURRENT_STATUS_LABELS: CURRENT_STATUS_LABELS,
      CURRENT_USE_TYPE_LABELS: CURRENT_USE_TYPE_LABELS,
      HISTORICAL_FUNCTION_LABELS: HISTORICAL_FUNCTION_LABELS,
      HISTORICAL_NO_DATA_KEY: HISTORICAL_NO_DATA_KEY,
      ERA_FILTER_CONTEXT_ONLY: ERA_FILTER_CONTEXT_ONLY,
      DEFAULT_ACTOR_LABEL: DEFAULT_ACTOR_LABEL,
      UNKNOWN_EPOCH_SUMMARY: UNKNOWN_EPOCH_SUMMARY
    },
    actorFilterLabel: actorFilterLabel,
    buildActorMap: buildActorMap,
    buildEraMap: buildEraMap,
    buildScopeLabel: buildScopeLabel,
    buildSocialRelations: buildSocialRelations,
    buildSpatialRelations: buildSpatialRelations,
    buildTemporalRelations: buildTemporalRelations,
    createState: createState,
    deriveFallbackActors: deriveFallbackActors,
    deriveFallbackEras: deriveFallbackEras,
    distanceMeters: distanceMeters,
    filterPlaces: filterPlaces,
    formatDistanceLabel: formatDistanceLabel,
    getActorKeysForEra: getActorKeysForEra,
    getAllActorKeys: getAllActorKeys,
    getEpochForEra: getEpochForEra,
    getEpochFunctionKeys: getEpochFunctionKeys,
    getEpochFunctionKeysForEra: getEpochFunctionKeysForEra,
    getEraScopedPlaces: getEraScopedPlaces,
    getEraSlugsForPlace: getEraSlugsForPlace,
    getPublicationIds: getPublicationIds,
    getSelectedPlace: getSelectedPlace,
    getSelectedStories: getSelectedStories,
    intersectList: intersectList,
    isAtlasPlace: isAtlasPlace,
    isUnknownEpochFunction: isUnknownEpochFunction,
    mapEraNames: mapEraNames,
    matchesEra: matchesEra,
    normalizeActor: normalizeActor,
    normalizeEra: normalizeEra,
    normalizePlace: normalizePlace,
    normalizeStory: normalizeStory,
    placeMatchesActor: placeMatchesActor,
    score: score,
    sortPlaces: sortPlaces,
    uniqueList: uniqueList,
    useTypeFilterLabel: useTypeFilterLabel
  };

  window.issSchoneweideAtlas = Atlas;
})();
