import {
  labelCopyAutologinLink,
  labelEditProfile,
  labelFullscreen,
  labelLogout
} from '../UserMenu/translatedLabels';
import { initialize } from './Header.utils';

export default (): void => {
  describe('User Menu', () => {
    beforeEach(() => {
      cy.intercept('PATCH', 'parameters', {
        theme: 'dark'
      }).as('updateTheme');
    });

    it('matches the current snapshot "user menu"', () => {
      initialize();
      cy.viewport(1200, 300);

      cy.get('[data-cy=clock]').as('clock');
      cy.get('@clock').should('be.visible');
      cy.get('@clock').contains('April 28, 2022');
      cy.get('@clock').contains('4:20 PM');

      cy.makeSnapshot();
    });

    it('does not display the clock for a width less than 769px', () => {
      initialize();
      cy.viewport(768, 500);
      cy.get('[data-cy=clock]').as('clock').should('not.be.visible');
      cy.makeSnapshot();
    });

    it('expands the popper when the user icon is clicked', () => {
      initialize();
      cy.get('[data-cy=userIcon]').as('userIcon');
      cy.get('@userIcon').contains('AA');
      cy.get('@userIcon').click();
      cy.get('[data-cy=popper]').as('popper');

      cy.get('@popper').should('be.visible');
      cy.get('@popper').contains('admin');
      cy.get('@popper').contains('Dark');
      cy.get('@popper').contains('Light');
      cy.get('@popper').contains('Logout');

      cy.makeSnapshot();
    });

    it('changes style when switch is clicked', () => {
      initialize();
      cy.get('[data-cy=userIcon]').click();
      cy.get('[data-cy=themeSwitch]').as('switchMode').should('be.visible');
      cy.get('@switchMode').click();
      cy.get('header').should('have.css', 'background-color', 'rgb(0, 0, 0)');
      cy.get('@switchMode').click();
      cy.get('header').should(
        'have.css',
        'background-color',
        'rgb(37, 88, 145)'
      );
    });

    it('navigates to the logout page when the button is clicked', () => {
      const navigate = initialize();

      cy.get('[data-cy=userIcon]').click();
      cy.contains(labelLogout)
        .click()
        .then(() => {
          expect(navigate).to.have.been.calledWith('/logout');
        });
    });

    it('copies the autologin link to the blipboard when the button is clicked', () => {
      initialize();

      cy.get('[data-cy=userIcon]').click();
      cy.contains(labelCopyAutologinLink).click();

      cy.window().then((win) => {
        win.navigator.clipboard.readText().then((text) => {
          expect(text).to.eq('LKEY-autologin');
        });
      });
    });

    it('navigates to the edit profile page when the button is clicked', () => {
      const navigates = initialize();

      cy.get('[data-cy=userIcon]').click();
      cy.contains(labelEditProfile)
        .click()
        .then(() => {
          expect(navigates).to.have.been.calledWith('/main.php?p=50104&o=c');
        });
    });

    it('closes the menu when the user icon is clicked once again', () => {
      initialize();

      cy.get('[data-cy=userIcon]').click();

      cy.contains(labelEditProfile).should('be.visible');

      cy.get('[data-cy=userIcon]').click();

      cy.contains(labelEditProfile).should('not.exist');
    });

    it('enters fullscreen mode when the corresponding icon is clicked', () => {
      initialize();

      cy.get('[data-cy=userIcon]').click();

      cy.contains(labelFullscreen).realClick();

      cy.contains(labelFullscreen).should('not.exist');
    });

    describe('User icon initials', () => {
      [
        {
          expected: 'JO',
          fullname: 'John',
          title: 'uses the first two letters when the full name is a single word'
        },
        {
          expected: 'JD',
          fullname: 'John_Doe',
          title:
            'uses the first letter of each word when the full name has two words separated by an underscore'
        },
        {
          expected: 'JD',
          fullname: 'John Doe',
          title:
            'uses the first letter of each word when the full name has two words separated by a space'
        },
        {
          expected: 'JD',
          fullname: 'John_Doe_Due',
          title:
            'uses the first letter of the first two words when the full name has more than two words'
        },
        {
          expected: 'AP',
          fullname: 'admin_plateforme',
          title:
            'uses the first letter of each word when the full name contains an underscore'
        },
        {
          expected: 'AD',
          fullname: '',
          title:
            'falls back to the username initials when the full name is empty',
          username: 'admin'
        },
        {
          expected: '?',
          fullname: '',
          title:
            'displays a placeholder when both the full name and username are empty',
          username: ''
        }
      ].forEach(({ fullname, expected, title, username }) => {
        it(title, () => {
          initialize({ user: { fullname, username } });

          cy.get('[data-cy=userIcon]').should('have.text', expected);
        });
      });
    });
  });
};
