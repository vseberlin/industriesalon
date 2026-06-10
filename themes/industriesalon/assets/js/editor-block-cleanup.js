(function (wp) {
  if (!wp || !wp.domReady || !wp.blocks) {
    return;
  }

  wp.domReady(function () {
    [
      'core/archives',
      'core/categories',
      'core/tag-cloud',
      'core/query-title'
    ].forEach(function (blockName) {
      if (wp.blocks.getBlockType(blockName)) {
        wp.blocks.unregisterBlockType(blockName);
      }
    });
  });
}(window.wp));
