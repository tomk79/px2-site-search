const $ = require('jquery');
const $script = $('script').last();
const params = {
	__dirname: $script.attr('src').replace(/[^\/]+$/, ''),
	engine_type: '$____engine_type____',
	path_controot: $script.attr('data-path-controot') || '$____data-path-controot____',
	local_storage_key: $script.attr('data-local-storage-key') || 'px2-site-search',
	allow_client_cache: isTrulyAttributeValue($script.attr('data-allow-client-cache')),
};

const DataLoader = require("./DataLoader.js");
const Searcher = require("./Searcher.js");
const UiSearchForm = require("./Ui/SearchForm.js");
const UiSearchDialog = require("./Ui/SearchDialog.js");

module.exports = function(){
	const self = this;
	const dataLoader = new DataLoader(this);
	const searcher = new Searcher(this);
	let documentList = null;

	/**
	 * 全文検索結果を得る
	 * @param {*} callback 
	 */
	this.getDocumentList = function(callback){
		if(this.params().engine_type == 'paprika'){
			callback(false);
			return;
		}
		if(documentList){
			callback(documentList);
			return;
		}
		dataLoader.load(function(data){
			documentList = data;
			searcher.setDocumentData(documentList.contents);
			callback(documentList);
		});
	}

	/**
	 * パラメータを取得する
	 * @returns {Object} params
	 */
	this.params = function(){
		return params;
	}

	/**
	 * 全文検索結果を得る
	 * @param {*} query 
	 * @param {*} callback 
	 */
	this.search = function(query, callback){
		const engine_type = this.params().engine_type;
		if(engine_type == 'paprika'){
			$.ajax({
				"url": `${this.params().__dirname}../search.php`,
				"data": {
					"q": query,
				},
				"timeout": 30*1000,
				"success": function(searchResult){
					callback(searchResult.result, searchResult.documentList);
				},
				"error": function(){
					console.error('Failed to load search.php');
				},
			});
		} else {
			this.getDocumentList(function(documentList){
				searcher.search(query, function(results){
					callback(results, documentList);
				});
			});
		}
	}

	/**
	 * 検索フォームを生成する
	 */
	this.createSearchForm = function(targetDiv){
		const uiSearchForm = new UiSearchForm(this);
		uiSearchForm.init(targetDiv);
	}

	/**
	 * 検索ダイアログを開く
	 */
	this.openSearchDialog = function(){
		const uiSearchDialog = new UiSearchDialog(this);
		uiSearchDialog.open();
	}
}

/**
 * 属性値をBooleanで評価する
 */
function isTrulyAttributeValue(attrValue){
	if(attrValue === true){
		return true;
	}
	if(attrValue === ""){ // NOTE: 値なしの属性が指定された場合
		return true;
	}
	const attrValueLowerCase = (typeof(attrValue) === typeof("string") ? attrValue.toLowerCase() : attrValue);
	switch(attrValueLowerCase){
		case "true":
		case "yes":
		case "1":
			return true;
	}
	return false;
}
