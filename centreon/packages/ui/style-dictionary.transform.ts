import * as StyleDictionaryPackage from "style-dictionary"

const themes = ["ui-light", "ui-dark"]

const tokensPath = "src/base/tokens"
const tokensSourcePath = "src/base/tokens/source"

const getStyleDictionaryBaseConfig = (): StyleDictionaryPackage.Config => ({
  source: [
    `${tokensSourcePath}/base.json`,
  ],
  platforms: {
    ts: {
      transformGroup: "ts/custom",
      buildPath: `${tokensPath}/themes/`,
      files: [
        {
          destination: `base.tokens.ts`,
          format: "javascript/es6",
        },
        {
          destination: `base.tokens.d.ts`,
          format: "typescript/es6-declarations",
        }
      ]
    },
    styleguide: {
      transformGroup: "styleguide",
      buildPath: `${tokensPath}/themes/`,
      files: [
        {
          destination: "base.tokens.json",
          format: "json/flat",
        },
      ],
    },
  },
})

/**
 * @doc https://github.com/amzn/style-dictionary/tree/main/examples/advanced/multi-brand-multi-platform
 */

const getStyleDictionaryConfig = (theme: string): StyleDictionaryPackage.Config => ({
  source: [
    `${tokensSourcePath}/base.json`,
    `${tokensSourcePath}/${theme}.json`,
  ],
  platforms: {
    ts: {
      transformGroup: "ts/custom",
      buildPath: `${tokensPath}/themes/`,
      files: [
        {
          destination: `${theme}.tokens.ts`,
          format: "javascript/es6",
        },
        {
          destination: `${theme}.tokens.d.ts`,
          format: "typescript/es6-declarations",
        }
      ]
    },
    styleguide: {
      transformGroup: "styleguide",
      buildPath: `${tokensPath}/themes/`,
      files: [
        {
          destination: `${theme}.tokens.json`,
          format: "json/flat",
        },
      ],
    },
  },
})

StyleDictionaryPackage.registerFormat({
  name: "json/flat",
  formatter(dictionary) {
    return JSON.stringify(dictionary.dictionary.allProperties, null, 2)
  },
})

// https://github.com/amzn/style-dictionary/issues/456#issuecomment-730606261
StyleDictionaryPackage.registerTransform({
  name: "shadow/css",
  type: "value",
  matcher(prop) {
    return prop.attributes?.category === "shadow"
  },
  transformer(prop) {
    const parseValue = ({ x, y, blur, spread, color }: any) => `${x} ${y} ${blur} ${spread} ${color}`

    return !Array.isArray(prop.original.value) ?
      parseValue(prop.original.value) :
      `(${prop.original.value.map(parseValue).join(", ")})`
  },
})

StyleDictionaryPackage.registerTransformGroup({
  name: "ts/custom",
  transforms: [
    // default : https://amzn.github.io/style-dictionary/#/transform_groups?id=js
    "attribute/cti", "size/rem",
    // adjust
    "name/cti/camel", "color/hex8",
    // custom
    "shadow/css",
  ],
})

StyleDictionaryPackage.registerTransformGroup({
  name: "styleguide",
  transforms: ["attribute/cti", "name/cti/kebab", "size/px", "color/css"],
})

// Generate the base tokens

// eslint-disable-next-line no-console
console.log("\x1b[33m%s\x1b[0m", "\n\n⚙️ base")

StyleDictionaryPackage
  .extend(getStyleDictionaryBaseConfig())
  .buildAllPlatforms()

// Generate the theme tokens

themes.forEach(theme => {
  // eslint-disable-next-line no-console
  console.log("\x1b[33m%s\x1b[0m", `\n\n⚙️ ${theme}`)

  StyleDictionaryPackage
    .extend(getStyleDictionaryConfig(theme))
    .buildAllPlatforms()
})
