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
    customDiffConfig: { threshold: 0.1 },
    customSnapshotsDir: `${cypressFolder}/visual-testing-snapshots`,
    failureThreshold: 0.06,
    failureThresholdType: 'percent'
  });
};

export default enableVisualTesting;
