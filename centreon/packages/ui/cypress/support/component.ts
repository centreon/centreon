import './commands'
import { addMatchImageSnapshotCommand } from 'cypress-image-snapshot/command';

const enableVisualTesting = (): void => {
  if (Cypress.config('isInteractive')) {
    Cypress.Commands.add('matchImageSnapshot', () => {
      cy.log('Skipping snapshot');
    });

    return;
  }

  addMatchImageSnapshotCommand({
    capture: 'viewport',
    customDiffConfig: { threshold: 0.1 },
    customSnapshotsDir: './cypress/visual-testing-snapshots',
    failureThreshold: 0.06,
    failureThresholdType: 'percent'
  });
};

enableVisualTesting();
