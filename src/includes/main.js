
const $ = require('jquery');
const $script = $('script').last();
const __dirname = $script.attr('src').replace(/[^\/]+$/, '');

const DataLoader = require("./DataLoader.js");
const dataLoader = new DataLoader();
const Searcher = require("./Searcher.js");
const searcher = new Searcher();

module.exports = function(){
	const self = this;
	let indexData = {};

	// 検索入力のイベントリスナー
	$('#cont-search-form')
		.on('submit', function(e){
			const keyword = $(this).find('input[name=q]').val();
			self.search(keyword, function(results){
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

	/**
	 * 全文検索結果を得る
	 * @param {*} query 
	 * @param {*} callback 
	 */
	this.search = function(query, callback){
		dataLoader.load(function(data){
			indexData = data;
			searcher.setDocumentData(indexData.contents);
			searcher.search(query, callback);
		});
	}

	/**
	 * 検索フォームを生成する
	 */
	this.createSearchForm = function(){
		// TODO: 実装する
	}

	/**
	 * 検索ダイアログを開く
	 */
	this.openSearchDialog = function(){
		// TODO: 実装する
	}
}
