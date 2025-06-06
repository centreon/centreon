import dotenv from 'dotenv';

dotenv.config();

export const DATABASE_URL = process.env.DATABASE_URL;
export const CENTREON_API_URL = process.env.CENTREON_API_URL;
export const LOGIN = process.env.LOGIN;
export const PASSWORD = process.env.PASSWORD;
export const API_BASE_URL = process.env.API_BASE_URL;
export const NUMBER_OF_HOSTS = process.env.NUMBER_OF_HOSTS;
export const NUMBER_OF_SERVICES = process.env.NUMBER_OF_SERVICES;
export const NUMBER_OF_HOSTGROUPS = process.env.NUMBER_OF_HOSTGROUPS;
export const NUMBER_OF_USERS = process.env.NUMBER_OF_USERS;
export const NUMBER_OF_METASERVICES = process.env.NUMBER_OF_METASERVICES;
export const NUMBER_OF_SERVICES_CATEGORIS = process.env.NUMBER_OF_SERVICES_CATEGORIS;
export const NUMBER_OF_SERVICE_GROUPS = process.env.NUMBER_OF_SERVICE_GROUPS;
