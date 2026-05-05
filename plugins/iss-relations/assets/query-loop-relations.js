(function () {
  if (!window.wp || !window.wp.hooks || !window.wp.element || !window.wp.compose) return;

  const settings = window.issRelationsSettings || {};
  const PLACE_POST_TYPE = settings.placePostType || 'register_place';
  const PLACE_TAXONOMY = settings.taxonomy || 'iss_place_ref';
  const SUPPORTED_POST_TYPES = Array.isArray(settings.supportedPostTypes)
    ? settings.supportedPostTypes
    : ['register_place', 'fuehrung', 'veranstaltung', 'ausstellung', 'projekt', 'post', 'page'];

  const el = window.wp.element.createElement;
  const Fragment = window.wp.element.Fragment;
  const addFilter = window.wp.hooks.addFilter;
  const createHigherOrderComponent =
    window.wp.compose && window.wp.compose.createHigherOrderComponent;
  const useSelect = window.wp.data && window.wp.data.useSelect;
  const InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
  const PanelBody = window.wp.components && window.wp.components.PanelBody;
  const FormTokenField = window.wp.components && window.wp.components.FormTokenField;

  if (!addFilter || !createHigherOrderComponent) return;

  function stripTags(value) {
    return String(value || '').replace(/<[^>]*>/g, '').trim();
  }

  function uniqueInts(values) {
    const seen = {};
    const ids = [];

    (Array.isArray(values) ? values : []).forEach(function (value) {
      const id = parseInt(value, 10);
      if (!id || seen[id]) return;
      seen[id] = true;
      ids.push(id);
    });

    return ids;
  }

  function parsePlaceIds(value) {
    if (Array.isArray(value)) {
      return uniqueInts(value);
    }

    if (typeof value === 'string') {
      return uniqueInts(value.split(/[\s,]+/));
    }

    return [];
  }

  function getPlaceLabel(post) {
    const title = stripTags(post && post.title && post.title.rendered ? post.title.rendered : '');
    return title !== '' ? title + ' (#' + post.id + ')' : '#' + post.id;
  }

  function getPlaceIdFromTerm(term) {
    const match = String(term && term.slug ? term.slug : '').match(/^place-(\d+)$/);
    return match ? parseInt(match[1], 10) : 0;
  }

  function buildSelectionState(posts, terms, selectedIds) {
    const tokenToPlaceId = {};
    const placeIdToToken = {};
    const placeIdToTermId = {};
    const suggestions = [];

    (Array.isArray(terms) ? terms : []).forEach(function (term) {
      const placeId = getPlaceIdFromTerm(term);
      if (placeId) {
        placeIdToTermId[placeId] = term.id;
      }
    });

    (Array.isArray(posts) ? posts : []).forEach(function (post) {
      const label = getPlaceLabel(post);
      tokenToPlaceId[label] = post.id;
      placeIdToToken[post.id] = label;
      suggestions.push(label);
    });

    return {
      suggestions: suggestions,
      selectedTokens: selectedIds
        .map(function (id) {
          return placeIdToToken[id] || '';
        })
        .filter(Boolean),
      tokenToPlaceId: tokenToPlaceId,
      placeIdToTermId: placeIdToTermId,
    };
  }

  function buildQueryAttributes(query, placeIds, placeIdToTermId) {
    const nextQuery = Object.assign({}, query || {});
    const nextTaxQuery = Object.assign({}, nextQuery.taxQuery || {});
    const termIds = uniqueInts(
      placeIds.map(function (placeId) {
        return placeIdToTermId[placeId] || 0;
      })
    );

    if (termIds.length > 0) {
      nextTaxQuery[PLACE_TAXONOMY] = termIds;
      nextQuery.taxQuery = nextTaxQuery;
      return nextQuery;
    }

    delete nextTaxQuery[PLACE_TAXONOMY];

    if (Object.keys(nextTaxQuery).length > 0) {
      nextQuery.taxQuery = nextTaxQuery;
    } else {
      delete nextQuery.taxQuery;
    }

    return nextQuery;
  }

  addFilter('blocks.registerBlockType', 'iss-relations/query-attributes', function (blockSettings, blockName) {
    if (blockName !== 'core/query') return blockSettings;

    blockSettings.attributes = Object.assign({}, blockSettings.attributes || {}, {
      issRelationPlaceIds: {
        type: 'array',
        default: [],
      },
    });

    return blockSettings;
  });

  const withIssRelationsQueryControls = createHigherOrderComponent(function (BlockEdit) {
    return function (props) {
      if (props.name !== 'core/query') {
        return el(BlockEdit, props);
      }

      const attrs = props.attributes || {};
      const query = attrs.query || {};
      const selectedIds = parsePlaceIds(attrs.issRelationPlaceIds);
      const currentPostType = query.postType || 'post';
      const isSupportedPostType = SUPPORTED_POST_TYPES.indexOf(currentPostType) !== -1;

      const records = useSelect
        ? useSelect(function (select) {
            const coreSelect = select('core');
            return {
              posts: coreSelect.getEntityRecords('postType', PLACE_POST_TYPE, {
                per_page: 100,
                orderby: 'title',
                order: 'asc',
                status: 'publish',
              }),
              terms: coreSelect.getEntityRecords('taxonomy', PLACE_TAXONOMY, {
                per_page: 100,
                hide_empty: false,
              }),
            };
          }, [])
        : { posts: [], terms: [] };

      const selection = buildSelectionState(records.posts, records.terms, selectedIds);

      function updateSelection(tokens) {
        const placeIds = uniqueInts(
          (Array.isArray(tokens) ? tokens : []).map(function (token) {
            return selection.tokenToPlaceId[token] || 0;
          })
        );

        props.setAttributes({
          issRelationPlaceIds: placeIds,
          query: buildQueryAttributes(query, placeIds, selection.placeIdToTermId),
        });
      }

      return el(
        Fragment,
        null,
        el(BlockEdit, props),
        InspectorControls && PanelBody
          ? el(
              InspectorControls,
              null,
              el(
                PanelBody,
                { title: 'Ortsbezug', initialOpen: false },
                el(
                  'p',
                  { className: 'components-base-control__help' },
                  isSupportedPostType
                    ? 'Filtert diesen Query Loop auf Inhalte, die mit ausgewählten Orten verknüpft sind.'
                    : 'Dieser Query Loop nutzt einen Inhaltstyp ohne Ortsindex. Der Filter wirkt nur mit verknüpften Inhaltstypen.'
                ),
                FormTokenField
                  ? el(FormTokenField, {
                      value: selection.selectedTokens,
                      suggestions: selection.suggestions,
                      onChange: updateSelection,
                      placeholder: 'Orte auswählen',
                    })
                  : null
              )
            )
          : null
      );
    };
  }, 'withIssRelationsQueryControls');

  addFilter('editor.BlockEdit', 'iss-relations/query-controls', withIssRelationsQueryControls);
})();
