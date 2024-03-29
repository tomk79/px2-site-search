/**
 * Highlight Text
 */
module.exports = function(text, keywords, options){
	options = options || {};
	const HIGHLIGHT_MAX_LENGTH = options.maxLength || 120;
	const HIGHLIGHT_SIDE_LENGTH = options.sideLength || 15;

	// キーワードが登場する位置を検索
	let aryKeywords = keywords.trim().split(/\s+/);
	let hits = [];
	const normalizedText = normalizeText(text);
	aryKeywords.every((keyword)=>{
		const normalizedKeyword = normalizeText(keyword);
		let start = 0;
		while(1){
			let index = normalizedText.indexOf(normalizedKeyword, start);
			if(index < 0){
				break;
			}
			hits.push({
				index: index,
				keyword: normalizedKeyword,
			});
			start = index + normalizedKeyword.length;
			continue;
		}
		return true;
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
	let virtualLength = 0;
	let returnText = '';
	let cursor = 0;
	hits.every((hitInfo)=>{
		if( virtualLength > HIGHLIGHT_MAX_LENGTH ){
			return false;
		}

		const textNode = shortenSideText(text.substring(cursor, hitInfo.index), HIGHLIGHT_SIDE_LENGTH, (returnText.length ? 'center' : 'left'));
		returnText += htmlspecialchars(textNode);
		virtualLength += textNode.length;
		cursor = hitInfo.index;

		const highlightNode = text.substring(cursor, cursor + hitInfo.keyword.length);
		returnText += '<mark>' + htmlspecialchars(highlightNode) + '</mark>';
		virtualLength += highlightNode.length;
		cursor = cursor + hitInfo.keyword.length;
		return true;
	});
	if( virtualLength <= HIGHLIGHT_MAX_LENGTH ){
		const textNode = shortenSideText(text.substring(cursor), HIGHLIGHT_SIDE_LENGTH, 'right');
		returnText += htmlspecialchars(textNode);
	}

	return returnText;
}

/**
 * 前後のテキストを短くする
 */
function shortenSideText(text, sideLength, direction){
	if( direction == 'center' ){
		if( text.length <= sideLength*2 ){
			return text;
		}
	}else{
		if( text.length <= sideLength ){
			return text;
		}
	}

	const leftStr = text.substring(0, sideLength);
	const rightStr = text.substring(text.length - sideLength, text.length);

	if( direction == 'left' ){
		return '...' + rightStr;
	}else if( direction == 'right' ){
		return leftStr + '...';
	}else if( direction == 'center' ){
		return leftStr + '...' + rightStr;
	}

	return text;
}

/**
 * normalize text
 */
function normalizeText(text){
	text = text.toLowerCase();
	return text;
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