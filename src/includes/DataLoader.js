const $ = require('jquery');

module.exports = function(main){

	let storage = {};
	const allowClientCache = (main.params().local_storage_key ? true : false);

    this.load = function(callback){
		if(allowClientCache){
			storage = JSON.parse(localStorage.getItem(main.params().local_storage_key)) ?? {};
			if( storage && storage.loadedAt && storage.loadedAt > Math.floor(Date.now()/1000) - (3*60*60)){
				callback(storage);
				return;
			}
		}

		$.ajax({
			"url": `${main.params().__dirname}../index.json`,
			"success": function(indexData){
				storage.loadedAt = Math.floor(Date.now()/1000);
				storage.contents = indexData.contents;

				if(allowClientCache){
					localStorage.setItem(main.params().local_storage_key, JSON.stringify(storage));
				}

                callback(storage);
			},
		});
    }

}
