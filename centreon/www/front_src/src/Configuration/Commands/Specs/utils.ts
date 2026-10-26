import commandDetails from './fixtures/commandDetails.json';
import listCommands from './fixtures/listCommands.json';
import listConnectors from './fixtures/listConnectors.json';
import listGlobalMacros from './fixtures/listGlobalMacros.json';
import listInstalledPlugins from './fixtures/listInstalledPlugins.json';
import listStandardMacros from './fixtures/listStandardMacros.json';

export const getListingResponse = (): typeof listCommands =>
  structuredClone(listCommands);

export const getCommandResponse = (): typeof commandDetails =>
  structuredClone(commandDetails);

export const getGlobalMacrosResponse = (): typeof listGlobalMacros =>
  structuredClone(listGlobalMacros);

export const getStandardMacrosResponse = (): typeof listStandardMacros =>
  structuredClone(listStandardMacros);

export const getInstalledPluginsResponse = (): typeof listInstalledPlugins =>
  structuredClone(listInstalledPlugins);

export const getConnectorsResponse = (): typeof listConnectors =>
  structuredClone(listConnectors);
