
const $ = require('jquery');

module.exports = function(main){
	const self = this;
	const href_prefix = main.params().path_controot.replace(/\/*$/, '');

	/**
	 * 検索フォームを生成する
	 */
	this.init = function(targetDiv){
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
				main.search(keyword, function(results, documentList){
					const $resultsDiv = $targetDiv.find(`.px2-site-search__result`);
					$resultsDiv.html("");

					if (results.length === 0) {
						$resultsDiv.html("<p>検索結果はありません</p>");
					} else {
						const list = document.createElement("ul");
						results.forEach((result) => {
							const listItem = document.createElement("li");
							let content = documentList.contents[result.id].content;
							if( content.length > 100 ){
								content = content.slice( 0, 97 ) + '...';
							}
							content = content.split("<").join("&lt;");
							listItem.innerHTML = `<h3><a href="${href_prefix}${documentList.contents[result.id].href}">${documentList.contents[result.id].title}</a></h3><p>${content}</p>`;
							list.appendChild(listItem);
						});
						$resultsDiv.append(list);
					}
				});
			});
	}
}
