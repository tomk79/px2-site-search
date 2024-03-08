const FlexSearch = require("flexsearch").default;

module.exports = function(){

	// --------------------------------------
	// FlexSearch インデックスの作成
	const index = new FlexSearch.Document({
		cache: true,
		encoder: "extra",
		tokenize: "full",
		preset: "match",
		document: {
			id: "id",
			store: ["title", "content"],
			index: [
				{ field: "title", boosting: 3 },
				{ field: "content", boosting: 1 },
			],
		},
	});

	this.setDocumentData = function(documentData){
		documentData.forEach((item, id) => {
			index.add(id, item);
		});
	}

	this.search = function(query, callback) {
		const origResults = index.search(query, {
			enrich: true,
			sort: true,
			bool: "or",
		});
		let done = {};
		let results = [];
		origResults.forEach((field)=>{
			field.result.forEach((item) => {
				if(done[item.id]){
					return;
				}
				done[item.id] = true;
				results.push(item);
			});
		});
		callback(results);
	}
}