import { InputType, type SelectEntry } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

import { hostFormPollersEndpoint } from '../../api/endpoints';
import { namedEntityDecoder } from '../../api/namedEntityDecoders';
import type { NamedEntity } from '../../models';
import {
  labelAlias,
  labelHostConfiguration,
  labelInvalidAddress,
  labelIpAddress,
  labelMonitoringServer,
  labelName,
  labelNameContainsForbiddenCharacters,
  labelNameMustNotStartWithModule,
  labelRequired,
  labelResolve
} from '../../translatedLabels';
import ResolveAddress from '../ResolveAddress';
import { buildSelector } from '../selector';
import type { FormSection } from './models';

// Uniqueness is not checked here: the server owns it, and a client check goes
// stale the moment someone else creates a host.
const nameMaxLength = 200;
const aliasMaxLength = 200;
const addressMaxLength = 255;
// The set `CreateHostInput` forbids, character for character. A backslash is
// not among them, so the form must not refuse `C:\temp` either.
const forbiddenNameCharacters = /^[^~!$%^&*"|'<>?,()=]*$/;
// The server spells the reserved prefix `_Module_` or `_Module `.
const moduleNamePrefix = /^_Module[_ ]/;

// An IPv4 or IPv6 address, or a name the poller can resolve. The underscore is
// deliberate: `Assert::HOSTNAME_PATTERN` allows it for NetBIOS and Active
// Directory names, so `srv_01` must reach the API rather than stop here.
const address =
  /^(\d{1,3}(\.\d{1,3}){3}|[\da-fA-F:]+:[\da-fA-F:.]*|\w([\w-]*\w)?(\.\w([\w-]*\w)?)*)$/;

interface HostConfigurationDetail {
  address: string;
  alias: string;
  name: string;
  poller: NamedEntity;
}

export const hostConfiguration: FormSection<HostConfigurationDetail> = {
  defaultValues: {
    address: '',
    alias: '',
    name: '',
    poller: null
  },
  detailDecoders: {
    address: JsonDecoder.string,
    // Left out of the response when the host has none.
    alias: JsonDecoder.optional(JsonDecoder.string).map((value) => value ?? ''),
    name: JsonDecoder.string,
    poller: JsonDecoder.object(namedEntityDecoder, 'Poller')
  },
  // One row at every panel width, as designed: the fields share it equally
  // and the resolve button keeps its own width.
  getInputs: ({ isCloudPlatform, t }) => [
    {
      fieldName: 'basic-information',
      grid: {
        // The resolve route does not exist on cloud.
        className: isCloudPlatform
          ? 'grid-cols-4'
          : 'grid-cols-[repeat(4,minmax(0,1fr))_auto]',
        columns: [
          {
            dataTestId: 'host-form-name',
            fieldName: 'name',
            label: t(labelName),
            required: true,
            type: InputType.Text
          },
          {
            dataTestId: 'host-form-alias',
            fieldName: 'alias',
            label: t(labelAlias),
            type: InputType.Text
          },
          {
            connectedAutocomplete: buildSelector({
              endpoint: hostFormPollersEndpoint,
              getOptionLabel: (option) => (option as SelectEntry)?.name,
              // The listing filter beside this field carries the same label
              // and reads every poller, where this one reads only the active
              // ones.
              queryKey: 'host-form-poller'
            }),
            dataTestId: 'host-form-poller',
            fieldName: 'poller',
            label: t(labelMonitoringServer),
            required: true,
            type: InputType.SingleConnectedAutocomplete
          },
          {
            dataTestId: 'host-form-address',
            fieldName: 'address',
            label: t(labelIpAddress),
            required: true,
            type: InputType.Text
          },
          ...(isCloudPlatform
            ? []
            : [
                {
                  custom: { Component: ResolveAddress },
                  dataTestId: 'host-form-address-resolve',
                  fieldName: 'address-resolve',
                  label: t(labelResolve),
                  type: InputType.Custom
                }
              ])
        ]
      },
      label: 'host-form-basic-information',
      type: InputType.Grid
    }
  ],
  getSchema: ({ t }) => ({
    address: string()
      .trim()
      .max(addressMaxLength)
      // Without this an empty address reports itself as invalid rather than
      // as missing, which the name field next to it does not do.
      .matches(address, {
        excludeEmptyString: true,
        message: t(labelInvalidAddress)
      })
      .required(t(labelRequired)),
    alias: string().trim().max(aliasMaxLength),
    // Both fields are trimmed the way the server normalises them, so blanks
    // report as missing instead of passing to a 422.
    name: string()
      .trim()
      .max(nameMaxLength)
      .matches(forbiddenNameCharacters, t(labelNameContainsForbiddenCharacters))
      .test(
        'is-not-a-module',
        t(labelNameMustNotStartWithModule),
        (value) => !moduleNamePrefix.test(value ?? '')
      )
      .required(t(labelRequired)),
    poller: object({
      id: number().required(t(labelRequired)),
      name: string()
    })
      .nullable()
      .required(t(labelRequired))
  }),
  label: labelHostConfiguration,
  toPayload: (values) => {
    const { name, alias, address, poller } = values as {
      address: string;
      alias: string;
      name: string;
      poller: { id: number } | null;
    };

    return {
      // Trimmed as the schema validates them: yup casts before checking the
      // length, so an untrimmed value passes `max` here and fails it server
      // side.
      address: address?.trim(),
      // The API has no empty alias: none is null.
      alias: alias?.trim() || null,
      name: name?.trim(),
      poller_id: poller?.id
    };
  }
};
