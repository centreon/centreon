import { getFieldsOwnedTwice } from '../../../ResourceForm/testUtils';
import { getHostTemplateResponse } from '../../Specs/utils';
import { blocks, type PlatformContext } from '.';

// Every form the platform can build. A host template has no mass change.
const contexts: Array<[name: string, context: PlatformContext]> = [
  ['onPrem', { isCloudPlatform: false }],
  [
    'onPrem with additive inheritance',
    { isAdditiveInheritanceEnabled: true, isCloudPlatform: false }
  ],
  ['cloud', { isCloudPlatform: true }]
];

describe('Host template field blocks', () => {
  it.each(contexts)('never write one field twice (%s)', (_name, context) => {
    expect(
      getFieldsOwnedTwice({
        blocks,
        context,
        detail: getHostTemplateResponse(1)
      })
    ).toEqual({
      defaultValues: [],
      detail: [],
      payload: [],
      updateModeFields: []
    });
  });
});
