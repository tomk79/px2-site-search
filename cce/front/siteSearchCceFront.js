window.siteSearchCceFront=function(t){var e=t.elm();e.innerHTML='\n\t\t<p>インデックスを更新します。</p>\n\t\t<p><button type="button" class="px2-btn px2-btn--primary cont-btn-create-index">インデックスを更新</button></p>\n\t',e.querySelector("button.cont-btn-create-index").addEventListener("click",(function(){var e=this;px2style.loading(),e.setAttribute("disabled",!0),t.pxCmd("/?PX=site_search.create_index",{progress:function(t,e){console.log("--- progress:",t,e)}},(function(t,n){console.log("---- pxCmdStdOut:",t,n),n?alert("[ERROR] インデックスの更新に失敗しました。"):alert("インデックスを更新しました。"),px2style.closeLoading(),e.removeAttribute("disabled")}))}))};