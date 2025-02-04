import { Method } from 'packages/ui/src';
import { equals } from 'ramda';
import { dashboardsFavoriteDeleteEndpoint } from './api/endpoints';
import { FavoriteAction } from './models';
import {
  labelAddToFavorites,
  labelDashboardAddedToFavorites,
  labelDashboardRemovedFromFavorites,
  labelRemoveFromFavorites
} from './translatedLabels';

export const manageAFavorite = ({ action, buttonAlias, requestsToWait }) => {
  const title = equals(action, FavoriteAction.add)
    ? labelAddToFavorites
    : labelRemoveFromFavorites;

  const updatedTitle = equals(action, FavoriteAction.add)
    ? labelRemoveFromFavorites
    : labelAddToFavorites;

  const labelSuccess = equals(action, FavoriteAction.add)
    ? labelDashboardAddedToFavorites
    : labelDashboardRemovedFromFavorites;
  cy.get(buttonAlias).trigger('mouseover');
  cy.findByText(title).should('be.visible');

  cy.get('@favoriteIcon').click();

  requestsToWait.forEach((aliasRequestToWait) => {
    cy.waitForRequest(aliasRequestToWait);
  });

  cy.findByText(labelSuccess).should('be.visible');

  cy.get('@favoriteIcon').trigger('mouseover');

  cy.findByText(updatedTitle).should('be.visible');
};

export const interceptDashboardsFavoriteDelete = (id: number) => {
  cy.interceptAPIRequest({
    alias: 'removeFavorite',
    method: Method.DELETE,
    path: `./api/latest${dashboardsFavoriteDeleteEndpoint(id)}`
  });
};
