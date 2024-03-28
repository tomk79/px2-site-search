/**
 * Highlight Text
 */
module.exports = function(text, keywords){

	// キーワードが登場する位置を検索
	let aryKeywords = keywords.trim().split(/\s+/);
	let hits = [];
	aryKeywords.forEach((keyword)=>{
		let start = 0;
		while(1){
			let index = text.indexOf(keyword, start);
			if(index < 0){
				break;
			}
			hits.push({
				index: index,
				keyword: keyword,
			});
			start = index + keyword.length;
			continue;
		}
	});

	// 先頭から並び替え
	hits.sort(function(a, b) {
		if (a.index < b.index) {
			return -1;
		} else if (a.index > b.index) {
			return 1;
		}
		return 0;
	});

	// ハイライト作成
	const HIGHTLIGHT_MAX_LENGT = 100;
	let virtualLength = 0;
	let returnText = '';
	let cursor = 0;
	hits.forEach((hitInfo)=>{
		if( virtualLength > HIGHTLIGHT_MAX_LENGT ){
			return;
		}

		const textNode = text.substring(cursor, hitInfo.index);
		returnText += htmlspecialchars(textNode);
		virtualLength += textNode.length;
		cursor = hitInfo.index;

		const highlightNode = text.substring(cursor, cursor + hitInfo.keyword.length);
		returnText += '<mark>' + htmlspecialchars(highlightNode) + '</mark>';
		virtualLength += highlightNode.length;
		cursor = cursor + hitInfo.keyword.length;
	});
	if( virtualLength <= HIGHTLIGHT_MAX_LENGT ){
		const textNode = text.substring(cursor);
		returnText += htmlspecialchars(textNode);
	}

	return returnText;
}

/**
 * htmlspecialchars
 */
function htmlspecialchars(text){
	text = text.split("&").join("&amp;");
	text = text.split("\"").join("&quot;");
	text = text.split("<").join("&lt;");
	text = text.split(">").join("&gt;");
	return text;
}