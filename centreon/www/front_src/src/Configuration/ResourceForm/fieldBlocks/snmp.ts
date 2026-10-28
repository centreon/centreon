import {
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { string } from 'yup';

import { getBlock, twoColumns } from '../grid';
import type { FieldBlock, ResourceOptions } from '../models';
import {
  labelNone,
  labelSnmpCommunity,
  labelSnmpVersion
} from '../translatedLabels';

const snmpCommunityMaxLength = 255;

const snmpVersions = ['1', '2c', '3'] as const;

type SnmpVersion = (typeof snmpVersions)[number];

const snmpVersionOptions = snmpVersions.map((version) => ({
  id: version,
  name: version
}));

type SnmpVersionOption = (typeof snmpVersionOptions)[number];

// Fallback to empty the field: the select cannot be cleared.
const noSnmpVersionId = 'none';

interface SnmpDetail {
  snmpCommunity: string;
  snmpVersion: SnmpVersionOption | null;
}

// The SNMP community and version the checks of a host use.
export const snmp = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<SnmpDetail> => ({
  defaultValues: { snmpCommunity: '', snmpVersion: null },
  detailDecoders: {
    // Write-only: never read back.
    snmpCommunity: JsonDecoder.constant(''),
    snmpVersion: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.oneOf(
          snmpVersionOptions.map((option) =>
            JsonDecoder.isExactly<SnmpVersion>(option.id).map(() => option)
          ),
          'SNMP version'
        )
      )
    ).map((value) => value ?? null)
  },
  detailKeyMap: {
    snmpCommunity: 'snmp_community',
    snmpVersion: 'snmp_version'
  },
  getInputs: ({ t }) => {
    const snmpVersion: InputPropsWithoutGroup = {
      autocomplete: {
        options: [
          { id: noSnmpVersionId, name: t(labelNone) },
          ...snmpVersionOptions
        ]
      },
      change: ({ setFieldValue, value }) =>
        setFieldValue(
          'snmpVersion',
          (value as SelectEntry | null)?.id === noSnmpVersionId ? null : value
        ),
      dataTestId: `${idPrefix}-snmp-version`,
      fieldName: 'snmpVersion',
      label: t(labelSnmpVersion),
      type: InputType.SingleAutocomplete
    };

    return {
      // Password managers take the text field before a password for its user
      // name, here the check command arguments.
      snmp: getBlock({
        className: twoColumns,
        columns: [
          {
            dataTestId: `${idPrefix}-snmp-community`,
            fieldName: 'snmpCommunity',
            label: t(labelSnmpCommunity),
            type: InputType.Password
          },
          snmpVersion
        ],
        idPrefix,
        name: 'snmp'
      })
    };
  },
  getSchema: () => ({
    snmpCommunity: string().max(snmpCommunityMaxLength)
  }),
  toPayload: (values) => {
    const { snmpCommunity, snmpVersion } = values as {
      snmpCommunity?: string;
      snmpVersion?: SnmpVersionOption | null;
    };

    return {
      // Write-only: empty means unchanged.
      ...(snmpCommunity && { snmp_community: snmpCommunity }),
      snmp_version: snmpVersion?.id ?? null
    };
  }
});
