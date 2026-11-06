import type { TFunction } from 'i18next';

import { getDetailDecoder, getInputBlocks, getPayload } from '../blocks';
import type { ResourceOptions } from '../models';
import {
  getActionUrlAndGeoCoordinatesBlock,
  getActionUrlBlock
} from './extendedInfos';

const t = ((label: string) => label) as TFunction;
const context = { isCloudPlatform: false };
const resource: ResourceOptions = { baseEndpoint: './api', idPrefix: 'form' };

const values = {
  extendedInfos: {
    actionUrl: ' https://example.com/act ',
    geoCoordinates: '48.85,2.35'
  }
};

const detail = {
  extended_informations: {
    action_url: 'https://example.com/act',
    geo_coordinates: '48.85,2.35'
  }
};

const getColumnNames = (
  blocks: ReturnType<typeof getInputBlocks>
): Record<string, Array<string>> =>
  Object.fromEntries(
    Object.entries(blocks).map(([id, block]) => [
      id,
      (block?.grid?.columns ?? []).map(({ fieldName }) => fieldName)
    ])
  );

describe('getActionUrlAndGeoCoordinatesBlock', () => {
  it('hands over the action URL and the coordinates, and sends both', () => {
    const block = getActionUrlAndGeoCoordinatesBlock({ resource });

    expect(getColumnNames(getInputBlocks([block], { ...context, t }))).toEqual({
      actionUrlAndGeoCoordinates: [
        'extendedInfos.actionUrl',
        'extendedInfos.geoCoordinates'
      ]
    });
    expect(getPayload([block], values, context)).toEqual({
      extended_informations: {
        action_url: 'https://example.com/act',
        geo_coordinates: '48.85,2.35'
      }
    });
  });
});

describe('getActionUrlBlock', () => {
  it('hands over the action URL alone and sends no coordinates', () => {
    const block = getActionUrlBlock({ resource });

    expect(getColumnNames(getInputBlocks([block], { ...context, t }))).toEqual({
      actionUrl: ['extendedInfos.actionUrl']
    });
    expect(getPayload([block], values, context)).toEqual({
      extended_informations: { action_url: 'https://example.com/act' }
    });
  });

  it('reads a detail carrying coordinates without them', () => {
    const decoded = getDetailDecoder({
      blocks: [getActionUrlBlock({ resource })],
      context,
      name: 'Resource'
    }).decode(detail);

    expect(decoded.isOk()).toBe(true);
    expect(decoded.isOk() && decoded.value).toEqual({
      extendedInfos: { actionUrl: 'https://example.com/act' }
    });
  });
});
