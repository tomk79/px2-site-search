const $ = require('jquery');
$(window).on('load', function(){
	let indexData = {};
	$.ajax({
		"url": "/caches/p/sitesearch/contents/index.json",
		"success": function(data){
			indexData = data;
		},
	});
	$('#cont-search-form')
		.on('submit', function(e){
			const keyword = $(this).find('input[name=q]').val();
			const searchResult = indexData.contents.filter(function(row){
				if(row.title.indexOf(keyword) >= 0){
					return true;
				}
				if(row.content.indexOf(keyword) >= 0){
					return true;
				}
				return false;
			});
			console.log(searchResult);
			$ul = $('<ul>');
			searchResult.forEach(function(row){
				const $li = $('<li>');
				$li.append($('<a>')
					.text(row.title)
					.attr({
						"href": row.href,
					})
				);
				$ul.append($li);
			});
			$('#cont-search-result').html('').append($ul);
		});
});
