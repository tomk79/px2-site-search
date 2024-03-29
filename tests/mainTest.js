var assert = require('assert');
const HighlightText = require('../src/assets/includes/HighlightText.js');

describe('Highlight text', function() {

	it("Highlight", function(done) {
		this.timeout(60*1000);

		assert.equal(
			HighlightText('abc def Foo hoge bar fuga foo.', 'foo bar'),
			'abc def <mark>Foo</mark> hoge <mark>bar</mark> fuga <mark>foo</mark>.'
		);

		assert.equal(
			HighlightText('abc def Foo hoge bar fugafugafugafugafuga foo hogehogehoge.', 'foo bar', {
				sideLength: 3,
			}),
			'...ef <mark>Foo</mark> hoge <mark>bar</mark> fu...ga <mark>foo</mark> ho...'
		);

		done();
	});

});
