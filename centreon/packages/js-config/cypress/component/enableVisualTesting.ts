import { addMatchImageSnapshotCommand } from '@simonsmith/cypress-image-snapshot/command';

const enableVisualTesting = (cypressFolder = 'cypress'): void => {
  if (Cypress.config('isInteractive')) {
    Cypress.Commands.add('matchImageSnapshot', () => {
      cy.log('Skipping snapshot');
    });

    return;
  }

  addMatchImageSnapshotCommand({
    capture: 'viewport',
    customDiffConfig: { threshold: 0.01 },
    customSnapshotsDir: `${cypressFolder}/visual-testing-snapshots`,
    failureThreshold: Cypress.env('updateSnapshots') === true ? 0 : 0.07,
    failureThresholdType: 'percent'
  });
};

export default enableVisualTesting;
