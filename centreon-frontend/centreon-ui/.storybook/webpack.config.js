const path = require("path");

module.exports = {
  module: {
    rules: [
      {
        test: /\.css$/,
        include: path.resolve(__dirname, ".."),
        use: [
          {
            loader: 'style-loader',
          },
          {
            loader: 'css-loader',
            options: {
              sourceMap: true,
            },
          },
        ],
      },
      // {
      //   test: /\.scss$/,
      //   loaders: ["style-loader", "css-loader", "sass-loader"],
      //   include: path.resolve(__dirname, ".."),
      // },
      {
        test: /\.scss$/,
        use: [
          "style-loader", // creates style nodes from JS strings
          {
            "loader": "css-loader",
            "options": {
              "modules": true,
              // "localIdentName": "[local]",
              "localIdentName": "[local]__[hash:base64:5]",
              "importLoaders": 1,
              "sourceMap": false
            }
          },
          "sass-loader", // compiles Sass to CSS, using Node Sass by default
        ],
        include: path.resolve(__dirname, "..")
      },
      {
        test: /\.(woff|woff2|eot|ttf|otf)$/,
        loader: "file-loader"
      },
      {
        test: /\.(?:png|jpg|svg)$/,
        loader: "url-loader",
        query: {
          limit: 10000
        }
      }
    ]
  }
};
