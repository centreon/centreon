import * as pptrTestingLibrary from 'pptr-testing-library';

import { GenerateReportForPageProps } from "models";
import { baseUrl } from '../../../defaults';

export const generateReportForResourceStatusPageFilterInteraction = async ({
  navigate,
  snapshot,
  startTimespan,
  endTimespan,
  page,
}: GenerateReportForPageProps): Promise<void> => {

  await page.setCacheEnabled(false);

  await navigate({
    name: 'Resource Status Cold navigation',
    url: `${baseUrl}monitoring/resources`,
  });


  const {getDocument, queries} = pptrTestingLibrary;
  const $document = await getDocument(page);
  const { getByText, getAllByRole, getByTestId} = queries
  

 
  await page.waitForSelector('button[aria-label="Clear filter"]');
     
  await startTimespan('Clear search bar');
  await page.click('button[aria-label="Clear filter"]')
  await endTimespan();

    
  await startTimespan('Display basic filters');
  await page.click('button[aria-label="Filter options"]')
  await getByText($document,'Show more filters')
  await endTimespan();
    
  await snapshot('Resource Status with basic filters Snapshot');

  await startTimespan('Display extended filters');
  const showMoreFiltersButton = await getByText($document,/Show more filters/i)
  showMoreFiltersButton.click()
  await endTimespan();

  await snapshot('Resource Status with basic and extended filters Snapshot');


  await startTimespan('Click host input');
  const input = await getByTestId($document,'host')
  input.click()
  await endTimespan();
    
  await startTimespan('select host name');
  const options = await  getAllByRole($document,'option')
  options[0].click()
  await endTimespan();

  await startTimespan('Click search button');
  const search = await getByText($document,/Search/i)
  search.click()
  await endTimespan();


  await snapshot('Resource Status filtered by host name Snapshot');


};
