const { ContextualAttributesFactory } = mw.testKitchen;

QUnit.module( 'ext.testKitchen/ContextualAttributesFactory', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.factory = new ContextualAttributesFactory();
	}
} ) );

QUnit.test( 'page.title uses wgTitle for non-special pages', function ( assert ) {
	mw.config.set( {
		wgTitle: 'Foo',
		wgCanonicalSpecialPageName: false
	} );

	const { page } = this.factory.newContextualAttributes();

	assert.strictEqual( page.title, 'Foo' );
} );

QUnit.test( 'page.title uses wgCanonicalSpecialPageName for special pages', function ( assert ) {
	mw.config.set( {
		// The localized special page title, e.g. on frwiki.
		wgTitle: 'Accueil_de_l’espace_personnel',
		wgCanonicalSpecialPageName: 'Homepage'
	} );

	const { page } = this.factory.newContextualAttributes();

	assert.strictEqual( page.title, 'Homepage' );
} );
