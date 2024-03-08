
const $ = require('jquery');

const DataLoader = require("./DataLoader.js");
const dataLoader = new DataLoader();
const Searcher = require("./Searcher.js");
const searcher = new Searcher();

module.exports = function(){

	$(window).on('load', function(){
		let indexData = {};
		dataLoader.load(function(data){
			indexData = data;

			// データのインデックス作成
			searcher.setDocumentData(indexData.contents);

			// 検索入力のイベントリスナー
			$('#cont-search-form')
				.on('submit', function(e){
					const keyword = $(this).find('input[name=q]').val();
					searcher.search(keyword, function(results){
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
					});
				});
		
		});

	});

}
