module.exports = function(text, keywords){
	if( text.length > 100 ){
		text = text.slice( 0, 97 ) + '...';
	}
	text = text.split("<").join("&lt;");
	return text;
}
