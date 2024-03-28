var assert = require('assert');
const HighlightText = require('../src/assets/includes/HighlightText.js');

describe('Highlight text', function() {

	it("Highlight", function(done) {
		this.timeout(60*1000);

		assert.equal(
			HighlightText('abc def Foo hoge bar fuga foo.', 'foo bar'),
			'abc def <mark>Foo</mark> hoge <mark>bar</mark> fuga <mark>foo</mark>.'
		);

		done();
	});

});
