import {
  labelCreationDate,
  labelCreator,
  labelLastUpdate,
  labelName,
  labelType,
  labelUpdateBy
} from '../translatedLabels';
import initialize from './initialize';

const columnToSort = [
  { id: 'name', label: labelName },
  { id: 'type', label: labelType },
  { id: 'created_by', label: labelCreator },
  { id: 'created_at', label: labelCreationDate },
  { id: 'updated_by', label: labelUpdateBy },
  { id: 'updated_at', label: labelLastUpdate }
];

export default (): void => {
  describe('Listing', () => {
    beforeEach(initialize);
    it('displays the first page of the ACC listing', () => {
      cy.contains('VMWare1');
      cy.contains('Description for VMWare1');

      cy.matchImageSnapshot();
    });
    it('sends a listing request with the selected limit when the corresponding button is clicked', () => {
      cy.get('#Rows\\ per\\ page').click();
      cy.contains(/^20$/).click();

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(JSON.parse(request.url.searchParams.get('limit'))).to.equal(20);
      });
    });

    it('sends a listing request with the selected page when the corresponding button is clicked', () => {
      cy.findByLabelText('Next page').click();

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(JSON.parse(request.url.searchParams.get('page'))).to.equal(2);
      });
    });
    it('executes a listing request with sort parameter when a sortable column is clicked`', () => {
      columnToSort.forEach(({ label, id }) => {
        const sortBy = id;

        cy.contains('VMWare1');
        cy.contains('VMWare2');

        cy.findByLabelText(`Column ${label}`).click();

        cy.waitForRequest('@getConnectors').then(({ request }) => {
          const sortParam = JSON.parse(request.url.searchParams.get('sort_by'));

          expect(sortParam).to.deep.equal({
            [sortBy]: 'desc'
          });
        });

        cy.matchImageSnapshot(
          `column sorting --  executes a listing request when the ${label} column is clicked`
        );
      });
    });
  });
};
