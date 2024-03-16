window.siteSearchCceFront = function(cceAgent){
    let $elm = cceAgent.elm();


    $elm.innerHTML = `
        <p>インデックスを更新します。</p>
        <p><button type="button" class="px2-btn px2-btn--primary cont-btn-create-index">インデックスを更新</button></p>
    `;

    $elm.querySelector('button').addEventListener('click', function(){
        cceAgent.gpi({
            'command': 'create_index'
        }, function(res){
            console.log('---- res:', res);
            alert(res);
        });
    });
}