
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
	this.createSearchForm = function(targetDiv){
		const $targetDiv = $(targetDiv);

		let $htmlSearchForm = $(
			`<div>
				<form action="javascript:;" method="get" id="px2-site-search__search-form">
					<input type="search" name="q" value="" class="px2-input" />
					<button class="px2-btn px2-btn--primary">検索</button>
				</form>
			</div>
			<div class="px2-site-search__result"></div>`
		);

		$targetDiv.append($htmlSearchForm);

		// 検索入力のイベントリスナー
		$targetDiv.find('#px2-site-search__search-form')
			.on('submit', function(e){
				const keyword = $(this).find('input[name=q]').val();
				self.search(keyword, function(results){
					const $resultsDiv = $targetDiv.find(`.px2-site-search__result`);
					$resultsDiv.html("");

					if (results.length === 0) {
						$resultsDiv.html("<p>検索結果はありません</p>");
					} else {
						const list = document.createElement("ul");
						results.forEach((result) => {
							const listItem = document.createElement("li");
							listItem.innerHTML = `<h3><a href="${indexData.contents[result.id].href}">${indexData.contents[result.id].title}</a></h3><p>${indexData.contents[result.id].content.split("<").join("&lt;")}</p>`;
							list.appendChild(listItem);
						});
						$resultsDiv.append(list);
					}
				});
			});
	}

	/**
	 * 検索ダイアログを開く
	 */
	this.openSearchDialog = function(){
		if(!window.px2style){
			console.error('px2style is required.');
			return;
		}

		// TODO: 実装する
		px2style.modal({
			"title": "Search",
		});
	}
}
