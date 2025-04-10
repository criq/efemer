module.exports = {
	plugins: [
		require("postcss-preset-env")({
			stage: 1, // Enable modern CSS features
			autoprefixer: { grid: true }, // Enable grid support
		}),
	],
};
