
const $ = require('jquery');
const $script = $('script').last();
const __dirname = $script.attr('src').replace(/[^\/]+$/, '');

module.exports = function(main){
	const self = this;

	let words = {
		"title": "SIte Search",
	};
	if( main.params().lang == 'ja' ){
		words.title = "サイト内検索";
	}

	/**
	 * 検索ダイアログを開く
	 */
	this.open = function(){
		if(!window.px2style){
			console.error('px2style is required.');
			return;
		}

		const $body = $('<div>');
		px2style.modal({
			"title": words.title,
			"body": $body,
			"buttons": [],
		});
		main.createSearchForm($body);
	}
}
