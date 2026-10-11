const js = require("@eslint/js");
const globals = require("globals");

module.exports = [
  {
    ignores: [
      "**/node_modules/**",
      "**/vendor/**",
      "**/*.min.js",
      "plugins/**/blocks/**/editor.asset.php",
    ],
  },
  js.configs.recommended,
  {
    files: ["tests/e2e/specs/**/*.js"],
    languageOptions: { sourceType: "commonjs", globals: { ...globals.node, ...globals.browser } },
  },
  {
    files: [
      "themes/industriesalon/**/*.js",
      "plugins/iss-*/**/*.js",
      "plugins/industriesalon-*/**/*.js",
    ],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: "script",
      globals: {
        ...globals.browser,
        ...globals.node,
        wp: "readonly",
        jQuery: "readonly",
        $: "readonly",
        ajaxurl: "readonly",
        L: "readonly",
        issRegisterImageSuggestionsAdmin: "readonly",
      },
    },
    rules: {
      "no-empty": [
        "error",
        {
          allowEmptyCatch: true,
        },
      ],
      "no-unused-vars": [
        "warn",
        {
          argsIgnorePattern: "^_",
          caughtErrors: "none",
          caughtErrorsIgnorePattern: ".*",
          varsIgnorePattern: "^_",
        },
      ],
    },
  },
  {
    files: ["plugins/iss-frontend/modules/programme/assets/booking.js", "plugins/iss-frontend/modules/programme/assets/timeline-navigation.js"],
    languageOptions: { sourceType: "module" },
  },
];
