import { equals } from 'ramda';

import { ComplexContent, SimpleContent } from './stories/contents';
import {
  ComplexData,
  SimpleData,
  complexData,
  simpleData
} from './stories/datas';

import { Node, StandaloneTree, TreeProps } from '.';

const validateTree = (tree): void => {
  if (!tree.children) {
    cy.contains(tree.data.name).should('be.visible');

    return;
  }

  cy.contains(tree.data.name).should('be.visible');
  tree.children.forEach((child) => {
    validateTree(child);
  });
};

interface InitializeProps
  extends Pick<TreeProps<SimpleData | ComplexData>, 'treeLink' | 'children'> {
  data?: Node<SimpleData | ComplexData>;
  isDefaultExpanded?: (data: SimpleData | ComplexData) => boolean;
}

const initializeStandaloneTree = ({
  data = simpleData,
  isDefaultExpanded = undefined,
  treeLink,
  children = SimpleContent
}: InitializeProps): void => {
  cy.adjustViewport();

  cy.mount({
    Component: (
      <div style={{ height: '99vh' }}>
        <StandaloneTree
          node={{ height: 70, isDefaultExpanded, width: 70 }}
          tree={data}
          treeLink={treeLink}
        >
          {children}
        </StandaloneTree>
      </div>
    )
  });
};

describe('Simple data tree', () => {
  it('displays the whole tree', () => {
    initializeStandaloneTree({});

    validateTree(simpleData);

    cy.makeSnapshot();
  });

  it("collapses a node's childrens when a node is clicked", () => {
    initializeStandaloneTree({});

    cy.contains(/^E$/).should('be.visible');
    cy.contains(/^E1$/).should('be.visible');

    cy.contains(/^C$/).click();

    cy.contains(/^E$/).should('not.exist');
    cy.contains(/^E1$/).should('not.exist');

    cy.makeSnapshot();
  });

  it("expands a node's childrens when a node is clicked", () => {
    initializeStandaloneTree({});

    cy.contains(/^A$/).click();

    cy.contains(/^A1$/).should('not.exist');
    cy.contains(/^A2$/).should('not.exist');
    cy.contains(/^A3$/).should('not.exist');
    cy.contains(/^C$/).should('not.exist');

    cy.contains(/^A$/).click();

    cy.contains(/^A1$/).should('be.visible');
    cy.contains(/^A2$/).should('be.visible');
    cy.contains(/^A3$/).should('be.visible');
    cy.contains(/^C$/).should('be.visible');

    cy.makeSnapshot();
  });

  it('cannot collapses a node when a leaf is clicked', () => {
    initializeStandaloneTree({});

    cy.contains(/^Z$/).click();

    cy.contains(/^Z$/).should('be.visible');

    cy.makeSnapshot();
  });

  it('expands nodes by default when a prop is set', () => {
    initializeStandaloneTree({
      isDefaultExpanded: (data: SimpleData) => equals('critical', data.status)
    });

    cy.contains(/^T$/).should('be.visible');
    cy.contains(/^A$/).should('be.visible');
    cy.contains(/^A3$/).should('be.visible');
    cy.contains(/^C$/).should('be.visible');
    cy.contains(/^E$/).should('be.visible');
    cy.contains(/^E1$/).should('be.visible');

    cy.contains(/^B1$/).should('not.exist');
    cy.contains(/^D1$/).should('not.exist');

    cy.makeSnapshot();
  });

  it('displays customized links when a prop is set', () => {
    initializeStandaloneTree({
      treeLink: {
        getStroke: ({ target }) => (target.status === 'ok' ? 'grey' : 'black'),
        getStrokeDasharray: ({ target }) =>
          target.status === 'ok' ? '5,5' : '0',
        getStrokeOpacity: ({ target }) => (target.status === 'ok' ? 0.8 : 1),
        getStrokeWidth: ({ target }) => (target.status === 'ok' ? 1 : 2)
      }
    });

    cy.contains(/^Z$/).should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Complex data tree', () => {
  it('cannot collapse a node when a node is not clickable', () => {
    initializeStandaloneTree({
      children: ComplexContent,
      data: complexData
    });

    cy.contains('BA 3').should('be.visible');

    cy.contains('BA 2').click();

    cy.contains('BA 3').should('be.visible');

    cy.makeSnapshot();
  });

  it('collapses a node when a node is clickable', () => {
    initializeStandaloneTree({
      children: ComplexContent,
      data: complexData
    });

    cy.contains('BA 3').should('be.visible');

    cy.contains('2').click();

    cy.contains('BA 3').should('not.exist');

    cy.makeSnapshot();
  });

  it('displays the tree with step links when a prop is set', () => {
    initializeStandaloneTree({
      treeLink: {
        type: 'step'
      }
    });

    cy.contains('T').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the tree with line links when a prop is set', () => {
    initializeStandaloneTree({
      treeLink: {
        type: 'line'
      }
    });

    cy.contains('T').should('be.visible');

    cy.makeSnapshot();
  });
});
