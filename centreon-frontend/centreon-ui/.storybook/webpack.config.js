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
      {
        test: /\.scss$/,
        loaders: ["style-loader", "css-loader", "sass-loader"],
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
