const FlexSearch = require("flexsearch").default;

module.exports = function(){

	// --------------------------------------
	// FlexSearch インデックスの作成
	const index = new FlexSearch.Document({
		tokenize: "full",
		cache: true,
		encoder: "icase",
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
		const results = index.search(query, {
			pluck: ["title", "content"],
			sort: true,
			bool: "or",
			enrich: true,
		});
		callback(results);
	}
}
