import { getFieldsOwnedTwice } from '../../../ResourceForm/testUtils';
import { getServiceResponse } from '../../Specs/utils';
import { blocks, type PlatformContext } from '.';

// Every form the platform and the mass change can build.
const contexts: Array<[name: string, context: PlatformContext]> = [
  ['onPrem', { isCloudPlatform: false }],
  [
    'onPrem with additive inheritance',
    { isAdditiveInheritanceEnabled: true, isCloudPlatform: false }
  ],
  ['cloud', { isCloudPlatform: true }],
  ['onPrem mass change', { isCloudPlatform: false, isMassChange: true }],
  ['cloud mass change', { isCloudPlatform: true, isMassChange: true }]
];

describe('Service field blocks', () => {
  it.each(contexts)('never write one field twice (%s)', (_name, context) => {
    expect(
      getFieldsOwnedTwice({ blocks, context, detail: getServiceResponse(0) })
    ).toEqual({
      defaultValues: [],
      detail: [],
      payload: [],
      updateModeFields: []
    });
  });
});
