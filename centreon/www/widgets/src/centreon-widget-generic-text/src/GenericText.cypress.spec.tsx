import Widget from '.';

const text =
  '{"root":{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Hel","type":"text","version":1},{"detail":0,"format":1,"mode":"normal","style":"","text":"lo","type":"text","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":" w","type":"text","version":1},{"detail":0,"format":8,"mode":"normal","style":"","text":"orl","type":"text","version":1},{"detail":0,"format":0,"mode":"normal","style":"","text":"d","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1},{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"centreon.com","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"link","version":1,"rel":"noopener","target":"_blank","url":"https://centreon.com"}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}';

const veryLongText =
  '{"root":{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi sodales eget dolor a finibus. Phasellus non tempus ligula. Praesent nec mauris vel nunc iaculis tempus. Phasellus fringilla libero ut aliquet sodales. Aliquam porta velit eget dui faucibus hendrerit. Ut consectetur ultricies ultrices. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Nulla ligula justo, vulputate consequat mauris a, pharetra lacinia ante. Vestibulum elementum mauris vel nisi pellentesque, in cursus dui lacinia. Quisque feugiat eu sem vel facilisis. Mauris sagittis bibendum purus nec posuere. Donec pulvinar lacus mauris, ut porta leo blandit sed. Etiam sed fermentum nulla. Vestibulum tristique aliquam ipsum finibus rutrum. Curabitur ac nisl dignissim, hendrerit libero eu, consequat lorem.","type":"text","version":1}],"direction":"ltr","format":"justify","indent":0,"type":"paragraph","version":1},{"children":[],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1},{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Cras id dolor vel ipsum iaculis eleifend. Nulla sed scelerisque metus. Morbi volutpat nunc vitae sapien luctus, ac vestibulum magna mattis. Donec imperdiet ut nisi eget varius. Fusce nisi est, bibendum et nibh non, dictum auctor risus. Nulla eget sem laoreet, finibus purus a, rhoncus arcu. Pellentesque ac risus elementum, ultrices est sed, imperdiet ligula. Integer malesuada arcu nisi, nec interdum lectus finibus sed. Sed augue odio, pellentesque in metus vitae, placerat suscipit elit. Quisque convallis gravida commodo.","type":"text","version":1}],"direction":"ltr","format":"justify","indent":0,"type":"paragraph","version":1},{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Ut mollis viverra sem, eget malesuada ex tristique quis. Quisque non mi rutrum, tempor enim sit amet, bibendum massa. Etiam vitae tempor sem. Aenean sed imperdiet libero. Nulla faucibus massa a odio dictum fringilla. Aliquam et libero sodales, finibus orci vitae, porttitor orci. Sed sagittis, diam ac consequat faucibus, nisi eros pellentesque lacus, non tincidunt metus eros lacinia risus.","type":"text","version":1}],"direction":"ltr","format":"justify","indent":0,"type":"paragraph","version":1}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}';

const initializeComponent = (text): void => {
  cy.viewport(300, 200);
  cy.mount({
    Component: <Widget panelOptions={{ text }} />
  });
};

describe('Generic Text Widget', () => {
  it('displays the generic text widget', () => {
    initializeComponent(text);

    cy.contains('Hello world').should('be.visible');
    cy.contains('centreon.com').should('be.visible');
    cy.get('a').should('have.attr', 'href', 'https://centreon.com');

    cy.matchImageSnapshot();
  });

  it('displays the generic text widget with a very long text', () => {
    initializeComponent(veryLongText);

    cy.contains('Lorem ipsum dolor sit amet').should('be.visible');

    cy.matchImageSnapshot();
  });
});
