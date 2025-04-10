const path = require("path");
const webpack = require("webpack");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const { CleanWebpackPlugin } = require("clean-webpack-plugin");

module.exports = [
	{
		mode: "development", // Use development mode for better watching behavior
		watch: true, // Enable file watching
		entry: {
			app: path.resolve(__dirname, "static", "javascript", "index.js"),
		},
		output: {
			path: path.resolve(__dirname, "public", "javascript"),
			publicPath: "/javascript/",
			// filename: "[name].[contenthash].js",
			filename: "[name].js",
		},
		module: {
			rules: [
				{
					test: /\.jsx?$/,
					include: path.resolve(__dirname, "static", "javascript"),
					exclude: /node_modules/,
					use: {
						loader: "babel-loader",
						options: {
							cacheDirectory: true,
						},
					},
				},
			],
		},
		resolve: {
			extensions: [".json", ".js", ".jsx"],
		},
		plugins: [
			new CleanWebpackPlugin(),
			new webpack.ProvidePlugin({
				$: "jquery",
				jQuery: "jquery",
				Popper: ["@popperjs/core", "default"],
			}),
		],
		optimization: {
			splitChunks: {
				chunks: "all",
			},
			minimize: true,
		},
		devtool: "source-map",
		cache: true,
	},
	{
		mode: "development",
		watch: true,
		entry: path.resolve(__dirname, "static", "scss", "screen.scss"),
		output: {
			path: path.resolve(__dirname, "public", "css"),
			publicPath: "/css/",
		},
		module: {
			rules: [
				{
					test: /\.s?css$/,
					use: [
						MiniCssExtractPlugin.loader,
						"css-loader",
						"postcss-loader",
						"sass-loader",
					],
				},
			],
		},
		plugins: [
			new CleanWebpackPlugin(),
			new MiniCssExtractPlugin({
				// filename: "[name].[contenthash].css",
				filename: "[name].css",
			}),
		],
	},
];
