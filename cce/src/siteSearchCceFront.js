window.siteSearchCceFront = function(cceAgent){
	let $elm = cceAgent.elm();

	$elm.innerHTML = `
		<p>インデックスを更新します。</p>
		<p><button type="button" class="px2-btn px2-btn--primary cont-btn-create-index">インデックスを更新</button></p>
	`;

	$elm.querySelector('button.cont-btn-create-index')
		.addEventListener('click', function(){
			const elm = this;
			px2style.loading();
			elm.setAttribute('disabled', true);

			cceAgent.pxCmd('/?PX=site_search.create_index',
				{
				},
				{
					"progress": function(data, error){
						console.log('--- progress:', data, error);
					}
				},
				function(pxCmdStdOut, error){
					console.log('---- pxCmdStdOut:', pxCmdStdOut, error);
					if(!error){
						alert('インデックスを更新しました。');
					}else{
						alert('[ERROR] インデックスの更新に失敗しました。');
					}
					px2style.closeLoading();
					elm.removeAttribute('disabled');
				});
		});
}