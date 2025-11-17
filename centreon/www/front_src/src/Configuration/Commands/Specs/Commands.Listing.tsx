import { labelName, labelType } from '../translatedLabels';
import initialize from './initialize';

const columnToSort = [
  { id: 'name', label: labelName },
  { id: 'type', label: labelType }
];

export default (): void => {
  describe('Listing', () => {
    beforeEach(initialize);
    it('displays the first page of the Commands listing', () => {
      cy.waitForRequest('@getCommands');

      cy.contains('check_host_alive');

      cy.matchImageSnapshot();
    });
    it('sends a listing request with the selected limit when the corresponding button is clicked', () => {
      cy.waitForRequest('@getCommands');

      cy.contains('check_host_alive');

      cy.get('#Rows\\ per\\ page').click();
      cy.contains(/^20$/).click();

      cy.contains('check_host_alive');

      cy.waitForRequest('@getCommands').then(({ request }) => {
        expect(request.url.href).include('itemsPerPage=20');
      });
    });

    it('sends a listing request with the selected page when the corresponding button is clicked', () => {
      cy.waitForRequest('@getCommands');
      cy.contains('check_host_alive');

      cy.findByLabelText('Next page').click();

      cy.waitForRequest('@getCommands').then(({ request }) => {
        expect(JSON.parse(request.url.searchParams.get('page'))).to.equal(2);
      });
    });
    it('executes a listing request with sort parameter when a sortable column is clicked`', () => {
      columnToSort.forEach(({ label, id }) => {
        cy.waitForRequest('@getCommands');

        cy.contains('check_host_alive');

        cy.findByLabelText(`Column ${label}`).click();

        cy.waitForRequest('@getCommands').then(({ request }) => {
          expect(request.url.href).include(`sort[${id}]=desc`);
        });

        cy.matchImageSnapshot(
          `column sorting --  executes a listing request when the ${label} column is clicked`
        );
      });
    });
  });
};
