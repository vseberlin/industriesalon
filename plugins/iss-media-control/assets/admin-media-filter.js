(function (wp, config) {
  if (!wp || !wp.media || !wp.media.view || !config || !Array.isArray(config.terms) || !config.terms.length) {
    return;
  }

  if (wp.media.view.AttachmentFilters && wp.media.view.AttachmentFilters.IssMediaOwner) {
    return;
  }

  var AttachmentFilters = wp.media.view.AttachmentFilters;
  var AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
  if (!AttachmentFilters || !AttachmentsBrowser) {
    return;
  }

  var requestVar = typeof config.requestVar === 'string' ? config.requestVar : 'iss_media_owner';
  var allLabel = typeof config.allLabel === 'string' ? config.allLabel : 'All media ownership';
  var allValue = typeof config.allValue === 'string' ? config.allValue : '__all';
  var defaultModalOwner = typeof config.defaultModalOwner === 'string' ? config.defaultModalOwner : 'editorial-upload';

  wp.media.view.AttachmentFilters.IssMediaOwner = AttachmentFilters.extend({
    id: 'media-attachment-owner-filter',

    createFilters: function () {
      var filters = {};
      var allProps = {};
      allProps[requestVar] = allValue;

      filters.all = {
        text: allLabel,
        props: allProps,
        priority: 10
      };

      config.terms.forEach(function (term) {
        if (!term || !term.slug || !term.name) {
          return;
        }

        var props = {};
        props[requestVar] = term.slug;

        filters[term.slug] = {
          text: term.name,
          props: props,
          priority: 20
        };
      });

      this.filters = filters;
    }
  });

  wp.media.view.AttachmentsBrowser = AttachmentsBrowser.extend({
    createToolbar: function () {
      AttachmentsBrowser.prototype.createToolbar.apply(this, arguments);

      if (!this.toolbar || !this.collection || !this.collection.props) {
        return;
      }

      if (!this.collection.props.get(requestVar)) {
        this.collection.props.set(requestVar, defaultModalOwner);
      }

      this.toolbar.set(
        'iss-media-owner-filter',
        new wp.media.view.AttachmentFilters.IssMediaOwner({
          controller: this.controller,
          collection: this.collection,
          model: this.collection.props,
          priority: -80
        }).render()
      );
    }
  });
})(window.wp, window.issMediaControl);
