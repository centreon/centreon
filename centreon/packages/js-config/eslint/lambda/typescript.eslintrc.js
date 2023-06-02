module.exports = {
    extends: [ '../node/typescript.eslintrc.js'],
    overrides: [
      {
        files: ["*.spec.js", "*.test.ts", "*.tests.ts"],
        rules: {
          "import/first": 0,
          "import/order": 0,
          "@typescript-eslint/ban-ts-comment": 0,
          "@typescript-eslint/no-explicit-any": 0
        }
      }
    ],
    rules: {
      "import/extensions": ["off"],
      "no-console": "off",
      "no-underscore-dangle": "off",
      "class-methods-use-this": "off",
      "@typescript-eslint/naming-convention": [
        "error",
        {
          format: ["camelCase", "PascalCase", "UPPER_CASE"],
          selector: "variable"
        },
        {
          filter: {
            match: false,
            regex: "(__esModule|.+-.+)"
          },
          format: ["snake_case", "camelCase", "PascalCase", "UPPER_CASE"],
          selector: "property",
          leadingUnderscore: "allow"
        },
        {
          filter: {
            match: false,
            regex: "^_$"
          },
          format: ["snake_case", "camelCase", "PascalCase"],
          selector: "parameter"
        }
      ],
      "@typescript-eslint/require-array-sort-compare": "error"
    },
    parserOptions: {
      project: ["./tsconfig.json"]
    }
  }
