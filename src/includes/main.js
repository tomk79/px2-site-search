
const $ = require('jquery');
const $script = $('script').last();
const params = {
	__dirname: $script.attr('src').replace(/[^\/]+$/, ''),
	path_controot: $script.attr('data-path-controot') || '/',
	local_storage_key: $script.attr('data-local-storage-key') || 'px2-site-search',
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
		if(documentList){
			callback(documentList);
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
		this.getDocumentList(function(documentList){
			searcher.search(query, function(results){
				callback(results, documentList);
			});
		});
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
