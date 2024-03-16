window.siteSearchCceFront = function(cceAgent){
    let $elm = cceAgent.elm();

    cceAgent.onBroadcast(function(message){
        console.info('Broadcast recieved:', message);
        alert(message.message);
    });


    $elm.innerHTML = `
        <p>管理画面拡張を読み込みました。</p>
        <p>GPIを呼び出すテスト</p>
        <p><button type="button" class="px2-btn">呼び出します。</button></p>
    `;

    $elm.querySelector('button').addEventListener('click', function(){
        cceAgent.gpi({
            'command': 'test-gpi-call'
        }, function(res){
            console.log('---- res:', res);
            alert(res);
        });
    });
}