import { InputType, type SelectEntry } from '@centreon/ui';

import type { TFunction } from 'i18next';
import { isNil } from 'ramda';
import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

import { optionalNamedEntityDecoder } from '../../../ResourceForm/api/decoders';
import { getBlock, twoColumnsFromNarrow } from '../../../ResourceForm/grid';
import type {
  FieldBlock,
  NamedEntity,
  ResourceOptions
} from '../../../ResourceForm/models';
import { buildSelector } from '../../../ResourceForm/selector';
import {
  labelMustBeAtMostCharacters,
  labelRequired
} from '../../../ResourceForm/translatedLabels';
import {
  labelHost,
  labelName,
  labelNameContainsForbiddenCharacters,
  labelServiceBasicInformation
} from '../../translatedLabels';

// Uniqueness on the host is left to the server, which alone sees the other
// services of that host.
const nameMaxLength = 200;
// The set the legacy `illegal_object_name_chars` option strips.
const forbiddenNameCharacters = /^[^~!$%^&*"|'<>?,()=]*$/;

// Written as is on a line of the engine configuration: no control character.
const hasControlCharacter = (value: string): boolean =>
  [...value].some((character) => {
    const code = character.charCodeAt(0);

    return code < 32 || code === 127;
  });

// What the server stores: trimmed, inner runs of spaces collapsed.
const normalizeName = (name: string | undefined): string =>
  (name ?? '').trim().replace(/\s{2,}/g, ' ');

interface ServiceIdentityDetail {
  host: NamedEntity | null;
  // Read for the panel header's switch, which enables and disables the service
  // on a request of its own: on a deep link there is no row to take it from.
  // Sent back on an update, which replaces the whole service.
  isActivated?: boolean;
  name: string;
}

const getServiceOwnSchema = (t: TFunction) => ({
  host: object({ id: number(), name: string() })
    .nullable()
    .required(t(labelRequired)),
  // Trimmed as the server normalises it, so blanks report as missing.
  name: string()
    .trim()
    // Measured on what the server stores.
    .test(
      'is-at-most-max-length',
      t(labelMustBeAtMostCharacters, {
        label: t(labelName),
        max: nameMaxLength
      }),
      (value) => normalizeName(value).length <= nameMaxLength
    )
    .matches(forbiddenNameCharacters, t(labelNameContainsForbiddenCharacters))
    .test(
      'has-no-control-character',
      t(labelNameContainsForbiddenCharacters),
      (value) => !hasControlCharacter(value ?? '')
    )
    .required(t(labelRequired))
});

// The name and host of one service. A mass change edits a selection, so it has
// neither.
export const getServiceIdentityBlock = ({
  hostsEndpoint,
  resource: { baseEndpoint, idPrefix }
}: {
  hostsEndpoint: string;
  resource: ResourceOptions;
}): FieldBlock<ServiceIdentityDetail> => ({
  // The host stays empty even when the form opens from a host's services.
  defaultValues: { host: null, name: '' },
  detailDecoders: {
    host: optionalNamedEntityDecoder('Host'),
    isActivated: JsonDecoder.optional(JsonDecoder.boolean),
    name: JsonDecoder.string
  },
  detailKeyMap: { isActivated: 'activated' },
  getInputs: ({ t }) => ({
    basicInformation: getBlock({
      className: twoColumnsFromNarrow,
      columns: [
        {
          dataTestId: `${idPrefix}-name`,
          fieldName: 'name',
          label: t(labelName),
          required: true,
          type: InputType.Text
        },
        {
          connectedAutocomplete: buildSelector({
            baseEndpoint,
            endpoint: hostsEndpoint,
            getOptionLabel: (option) => (option as SelectEntry)?.name,
            queryKey: `${idPrefix}-host`
          }),
          dataTestId: `${idPrefix}-host`,
          fieldName: 'host',
          label: t(labelHost),
          required: true,
          type: InputType.SingleConnectedAutocomplete
        }
      ],
      idPrefix,
      name: 'basic-information',
      title: t(labelServiceBasicInformation)
    })
  }),
  getSchema: ({ t }) => getServiceOwnSchema(t),
  isAvailable: ({ isMassChange }) => !isMassChange,
  toPayload: (values) => {
    const { host, isActivated, name } = values as {
      host?: { id: number } | null;
      isActivated?: boolean;
      name?: string;
    };

    // Only an opened service has one: a new one is created enabled.
    const activation = isNil(isActivated) ? {} : { activated: isActivated };

    return {
      ...activation,
      host_id: host?.id ?? null,
      name: normalizeName(name)
    };
  }
});
