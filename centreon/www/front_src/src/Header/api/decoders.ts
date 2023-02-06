import { JsonDecoder } from 'ts.data.json';

const counterDecoder = JsonDecoder.object({
    total: JsonDecoder.string,
    unhandled: JsonDecoder.string,
}, 'counter')

export const serviceStatusDecoder = JsonDecoder.object({
    critical: counterDecoder,
    ok: JsonDecoder.string,
    pending: JsonDecoder.string,
    refreshTime: JsonDecoder.number,
    total: JsonDecoder.number,
    time: JsonDecoder.number,
    unknown: counterDecoder,
    warning: counterDecoder,
}, 'serviceStatusResponse');

export const hostStatusDecoder = JsonDecoder.object({
    down: counterDecoder,
    ok: JsonDecoder.string,
    pending: JsonDecoder.string,
    refreshTime: JsonDecoder.number,
    total: JsonDecoder.number,
    time: JsonDecoder.number,
    unreachable: counterDecoder,
}, 'hostStatusResponse');

const issueDecoder = JsonDecoder.object({
    critical: JsonDecoder.number,
    total: JsonDecoder.number,
    warning: JsonDecoder.number,
}, 'issue')

export const pollerIssuesDecoder = JsonDecoder.object({
    database: JsonDecoder.optional(issueDecoder),
    latency: JsonDecoder.optional(issueDecoder),
    stability: JsonDecoder.optional(issueDecoder),
    refreshTime: JsonDecoder.number,
    total: JsonDecoder.number,
}, 'pollerIssuesDecoder')