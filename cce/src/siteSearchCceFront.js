window.siteSearchCceFront = function(cceAgent){
	let $elm = cceAgent.elm();

	$elm.innerHTML = `
		<p>インデックスを更新します。</p>
		<p><button type="button" class="px2-btn px2-btn--primary cont-btn-create-index">インデックスを更新</button></p>
	`;

	$elm.querySelector('button')
		.addEventListener('click', function(){
			const elm = this;
			px2style.loading();
			elm.setAttribute('disabled', true);

			cceAgent.gpi({
				'command': 'create_index'
			}, function(res){
				console.log('---- res:', res);
				if(res.result){
					alert('インデックスを更新しました。');
				}else{
					alert('[ERROR] インデックスの更新に失敗しました。');
				}
				px2style.closeLoading();
				elm.removeAttribute('disabled');
			});
		});
}