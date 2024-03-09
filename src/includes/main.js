
const $ = require('jquery');
const $script = $('script').last();
const __dirname = $script.attr('src').replace(/[^\/]+$/, '');

const DataLoader = require("./DataLoader.js");
const dataLoader = new DataLoader();
const Searcher = require("./Searcher.js");
const searcher = new Searcher();
const UiSearchForm = require("./Ui/SearchForm.js");
const UiSearchDialog = require("./Ui/SearchDialog.js");

module.exports = function(){
	const self = this;
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
