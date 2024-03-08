const $script = $('script').last();
const __dirname = $script.attr('src').replace(/[^\/]+$/, '');

module.exports = function(){

    this.load = function(callback){
		$.ajax({
			"url": `${__dirname}../index.json`,
			"success": function(data){
                callback(data);
			},
		});
    }

}
