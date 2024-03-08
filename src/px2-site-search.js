const FlexSearch = require("flexsearch").default;
const $ = require('jquery');
const $script = $('script').last();
const __dirname = $script.attr('src').replace(/[^\/]+$/, '');

$(window).on('load', function(){
	let indexData = {};
	$.ajax({
		"url": `${__dirname}../index.json`,
		"success": function(data){
			indexData = data;

			// --------------------------------------
			// FlexSearch インデックスの作成
			const index = new FlexSearch.Document({
				tokenize: "full",
				cache: true,
				encoder: "icase",
				document: {
					id: "id",
					store: ["title", "content"],
					index: [
						{ field: "title", boosting: 3 },
						{ field: "content", boosting: 1 },
					],
				},
			});

			// データのインデックス作成
			indexData.contents.forEach((item, id) => {
				index.add(id, item);
			});

			// 検索関数
			function search(query) {
				const results = index.search(query, {
					pluck: ["title", "content"],
					sort: true,
					bool: "or",
					enrich: true,
				});
				displayResults(results);
			}

			// 検索結果の表示
			function displayResults(results) {
				const resultsDiv = document.getElementById(`cont-search-result`);
				resultsDiv.innerHTML = "";

				if (results.length === 0) {
					resultsDiv.innerHTML = "<p>検索結果はありません</p>";
				} else {
					const list = document.createElement("ul");
					results.forEach((result) => {
						const listItem = document.createElement("li");
						listItem.innerHTML = `<h3><a href="${indexData.contents[result.id].href}">${indexData.contents[result.id].title}</a></h3><p>${indexData.contents[result.id].content.split("<").join("&lt;")}</p>`;
						list.appendChild(listItem);
					});
					resultsDiv.appendChild(list);
				}
			}

			// 検索入力のイベントリスナー
			$('#cont-search-form')
				.on('submit', function(e){
					const keyword = $(this).find('input[name=q]').val();
					search(keyword);
				});

		},
	});
});
