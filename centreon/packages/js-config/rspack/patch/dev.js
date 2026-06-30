module.exports = {
	getDevConfiguration: () => ({
		cache: true,
		devtool: "eval-cheap-module-source-map",
		lazyCompilation: false,
		optimization: {
			splitChunks: false,
		},
	}),
};
