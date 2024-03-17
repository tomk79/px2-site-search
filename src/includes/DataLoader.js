const $ = require('jquery');

module.exports = function(main){

	let storage = {};

    this.load = function(callback){
		const isAllowClientCache = (main.params().local_storage_key && main.params().allow_client_cache ? true : false);

		if(isAllowClientCache){
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

				if(isAllowClientCache){
					localStorage.setItem(main.params().local_storage_key, JSON.stringify(storage));
				}

                callback(storage);
			},
		});
    }

}
