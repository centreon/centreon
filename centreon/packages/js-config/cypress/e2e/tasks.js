"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
var __generator = (this && this.__generator) || function (thisArg, body) {
    var _ = { label: 0, sent: function() { if (t[0] & 1) throw t[1]; return t[1]; }, trys: [], ops: [] }, f, y, t, g;
    return g = { next: verb(0), "throw": verb(1), "return": verb(2) }, typeof Symbol === "function" && (g[Symbol.iterator] = function() { return this; }), g;
    function verb(n) { return function (v) { return step([n, v]); }; }
    function step(op) {
        if (f) throw new TypeError("Generator is already executing.");
        while (g && (g = 0, op[0] && (_ = 0)), _) try {
            if (f = 1, y && (t = op[0] & 2 ? y["return"] : op[0] ? y["throw"] || ((t = y["return"]) && t.call(y), 0) : y.next) && !(t = t.call(y, op[1])).done) return t;
            if (y = 0, t) op = [op[0] & 2, t.value];
            switch (op[0]) {
                case 0: case 1: t = op; break;
                case 4: _.label++; return { value: op[1], done: false };
                case 5: _.label++; y = op[1]; op = [0]; continue;
                case 7: op = _.ops.pop(); _.trys.pop(); continue;
                default:
                    if (!(t = _.trys, t = t.length > 0 && t[t.length - 1]) && (op[0] === 6 || op[0] === 2)) { _ = 0; continue; }
                    if (op[0] === 3 && (!t || (op[1] > t[0] && op[1] < t[3]))) { _.label = op[1]; break; }
                    if (op[0] === 6 && _.label < t[1]) { _.label = t[1]; t = op; break; }
                    if (t && _.label < t[2]) { _.label = t[2]; _.ops.push(op); break; }
                    if (t[2]) _.ops.pop();
                    _.trys.pop(); continue;
            }
            op = body.call(thisArg, _);
        } catch (e) { op = [6, e]; y = 0; } finally { f = t = 0; }
        if (op[0] & 5) throw op[1]; return { value: op[0] ? op[1] : void 0, done: true };
    }
};
Object.defineProperty(exports, "__esModule", { value: true });
/* eslint-disable no-console */
var child_process_1 = require("child_process");
var fs_1 = require("fs");
var tar_fs_1 = require("tar-fs");
var testcontainers_1 = require("testcontainers");
var promise_1 = require("mysql2/promise");
exports.default = (function (on) {
    var dockerEnvironment = null;
    var containers = {};
    var getContainer = function (containerName) {
        var container;
        if (dockerEnvironment !== null) {
            container = dockerEnvironment.getContainer("".concat(containerName, "-1"));
        }
        else if (containers[containerName]) {
            container = containers[containerName];
        }
        else {
            throw new Error("Cannot get container ".concat(containerName));
        }
        return container;
    };
    on('task', {
        copyFromContainer: function (_a) {
            var destination = _a.destination, serviceName = _a.serviceName, source = _a.source;
            return __awaiter(void 0, void 0, void 0, function () {
                var container, error_1;
                return __generator(this, function (_b) {
                    switch (_b.label) {
                        case 0:
                            _b.trys.push([0, 3, , 4]);
                            if (!(dockerEnvironment !== null)) return [3 /*break*/, 2];
                            container = dockerEnvironment.getContainer("".concat(serviceName, "-1"));
                            return [4 /*yield*/, container
                                    .copyArchiveFromContainer(source)
                                    .then(function (archiveStream) {
                                    return new Promise(function (resolve) {
                                        var dest = tar_fs_1.default.extract(destination);
                                        archiveStream.pipe(dest);
                                        dest.on('finish', resolve);
                                    });
                                })];
                        case 1:
                            _b.sent();
                            _b.label = 2;
                        case 2: return [3 /*break*/, 4];
                        case 3:
                            error_1 = _b.sent();
                            console.error(error_1);
                            return [3 /*break*/, 4];
                        case 4: return [2 /*return*/, null];
                    }
                });
            });
        },
        createDirectory: function (directoryPath) { return __awaiter(void 0, void 0, void 0, function () {
            return __generator(this, function (_a) {
                if (!(0, fs_1.existsSync)(directoryPath)) {
                    (0, fs_1.mkdirSync)(directoryPath, { recursive: true });
                }
                return [2 /*return*/, null];
            });
        }); },
        execInContainer: function (_a) {
            var command = _a.command, name = _a.name;
            return __awaiter(void 0, void 0, void 0, function () {
                var _b, exitCode, output;
                return __generator(this, function (_c) {
                    switch (_c.label) {
                        case 0: return [4 /*yield*/, getContainer(name).exec([
                                'bash',
                                '-c',
                                command
                            ])];
                        case 1:
                            _b = _c.sent(), exitCode = _b.exitCode, output = _b.output;
                            return [2 /*return*/, { exitCode: exitCode, output: output }];
                    }
                });
            });
        },
        getContainerId: function (containerName) {
            return getContainer(containerName).getId();
        },
        getContainerIpAddress: function (containerName) {
            var container = getContainer(containerName);
            var networkNames = container.getNetworkNames();
            return container.getIpAddress(networkNames[0]);
        },
        getContainersLogs: function () { return __awaiter(void 0, void 0, void 0, function () {
            var dockerode, loggedContainers, error_2;
            return __generator(this, function (_a) {
                switch (_a.label) {
                    case 0:
                        _a.trys.push([0, 3, , 4]);
                        return [4 /*yield*/, (0, testcontainers_1.getContainerRuntimeClient)()];
                    case 1:
                        dockerode = (_a.sent()).container.dockerode;
                        return [4 /*yield*/, dockerode.listContainers()];
                    case 2:
                        loggedContainers = _a.sent();
                        return [2 /*return*/, loggedContainers.reduce(function (acc, container) {
                                var containerName = container.Names[0].replace('/', '');
                                acc[containerName] = (0, child_process_1.execSync)("docker logs -t ".concat(container.Id), {
                                    stdio: 'pipe'
                                }).toString('utf8');
                                return acc;
                            }, {})];
                    case 3:
                        error_2 = _a.sent();
                        console.warn('Cannot get containers logs');
                        console.warn(error_2);
                        return [2 /*return*/, null];
                    case 4: return [2 /*return*/];
                }
            });
        }); },
        requestOnDatabase: function (_a) {
            var database = _a.database, query = _a.query;
            return __awaiter(void 0, void 0, void 0, function () {
                var container, client, _b, rows, fields;
                return __generator(this, function (_c) {
                    switch (_c.label) {
                        case 0:
                            container = null;
                            if (dockerEnvironment !== null) {
                                container = dockerEnvironment.getContainer('db-1');
                            }
                            else {
                                container = getContainer('web');
                            }
                            return [4 /*yield*/, (0, promise_1.createConnection)({
                                    database: database,
                                    host: container.getHost(),
                                    password: 'centreon',
                                    port: container.getMappedPort(3306),
                                    user: 'centreon'
                                })];
                        case 1:
                            client = _c.sent();
                            return [4 /*yield*/, client.execute(query)];
                        case 2:
                            _b = _c.sent(), rows = _b[0], fields = _b[1];
                            return [4 /*yield*/, client.end()];
                        case 3:
                            _c.sent();
                            return [2 /*return*/, [rows, fields]];
                    }
                });
            });
        },
        startContainer: function (_a) {
            var command = _a.command, image = _a.image, name = _a.name, _b = _a.portBindings, portBindings = _b === void 0 ? [] : _b;
            return __awaiter(void 0, void 0, void 0, function () {
                var container, _c, _d;
                return __generator(this, function (_e) {
                    switch (_e.label) {
                        case 0: return [4 /*yield*/, new testcontainers_1.GenericContainer(image).withName(name)];
                        case 1:
                            container = _e.sent();
                            portBindings.forEach(function (_a) {
                                var source = _a.source, destination = _a.destination;
                                container = container.withExposedPorts({
                                    container: source,
                                    host: destination
                                });
                            });
                            if (command) {
                                container
                                    .withCommand(['bash', '-c', command])
                                    .withWaitStrategy(testcontainers_1.Wait.forSuccessfulCommand('ls'));
                            }
                            _c = containers;
                            _d = name;
                            return [4 /*yield*/, container.start()];
                        case 2:
                            _c[_d] = _e.sent();
                            return [2 /*return*/, container];
                    }
                });
            });
        },
        startContainers: function (_a) {
            var _b = _a.composeFilePath, composeFilePath = _b === void 0 ? "".concat(__dirname, "/../../../../../.github/docker/") : _b, databaseImage = _a.databaseImage, openidImage = _a.openidImage, profiles = _a.profiles, samlImage = _a.samlImage, webImage = _a.webImage;
            return __awaiter(void 0, void 0, void 0, function () {
                var composeFile, error_3;
                var _c;
                return __generator(this, function (_d) {
                    switch (_d.label) {
                        case 0:
                            _d.trys.push([0, 2, , 3]);
                            composeFile = 'docker-compose.yml';
                            return [4 /*yield*/, (_c = new testcontainers_1.DockerComposeEnvironment(composeFilePath, composeFile)
                                    .withEnvironment({
                                    MYSQL_IMAGE: databaseImage,
                                    OPENID_IMAGE: openidImage,
                                    SAML_IMAGE: samlImage,
                                    WEB_IMAGE: webImage
                                }))
                                    .withProfiles.apply(_c, profiles).withWaitStrategy('web', testcontainers_1.Wait.forHealthCheck())
                                    .up()];
                        case 1:
                            dockerEnvironment = _d.sent();
                            return [2 /*return*/, null];
                        case 2:
                            error_3 = _d.sent();
                            if (error_3 instanceof Error) {
                                console.error(error_3.message);
                            }
                            throw error_3;
                        case 3: return [2 /*return*/];
                    }
                });
            });
        },
        stopContainer: function (_a) {
            var name = _a.name;
            return __awaiter(void 0, void 0, void 0, function () {
                var container;
                return __generator(this, function (_b) {
                    switch (_b.label) {
                        case 0:
                            if (!containers[name]) return [3 /*break*/, 2];
                            container = containers[name];
                            return [4 /*yield*/, container.stop()];
                        case 1:
                            _b.sent();
                            delete containers[name];
                            _b.label = 2;
                        case 2: return [2 /*return*/, null];
                    }
                });
            });
        },
        stopContainers: function () { return __awaiter(void 0, void 0, void 0, function () {
            return __generator(this, function (_a) {
                switch (_a.label) {
                    case 0:
                        if (!(dockerEnvironment !== null)) return [3 /*break*/, 2];
                        return [4 /*yield*/, dockerEnvironment.down()];
                    case 1:
                        _a.sent();
                        dockerEnvironment = null;
                        _a.label = 2;
                    case 2: return [2 /*return*/, null];
                }
            });
        }); },
        waitOn: function (url) { return __awaiter(void 0, void 0, void 0, function () {
            return __generator(this, function (_a) {
                (0, child_process_1.execSync)("npx wait-on ".concat(url));
                return [2 /*return*/, null];
            });
        }); }
    });
});
