stage('Source') {
  node {
    sh 'cd /opt/centreon-build && git pull && cd -'
    dir('centreon-awie') {
      checkout scm
    }
    sh '/opt/centreon-build/jobs/awie/3.4/mon-awie-source.sh'
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
  }
}

try {
  stage('Unit tests') {
    parallel 'centos6': {
      /*
      node {
        sh 'cd /opt/centreon-build && git pull && cd -'
        sh '/opt/centreon-build/jobs/awie/3.4/mon-awie-unittest.sh centos6'
        step([
          $class: 'XUnitBuilder',
          thresholds: [
            [$class: 'FailedThreshold', failureThreshold: '0'],
            [$class: 'SkippedThreshold', failureThreshold: '0']
          ],
          tools: [[$class: 'PHPUnitJunitHudsonTestType', pattern: 'ut.xml']]
        ])
      }
      */
    },
    'centos7': {
      /*
      node {
        sh 'cd /opt/centreon-build && git pull && cd -'
        sh '/opt/centreon-build/jobs/awie/3.4/mon-awie-unittest.sh centos7'
        step([
          $class: 'XUnitBuilder',
          thresholds: [
            [$class: 'FailedThreshold', failureThreshold: '0'],
            [$class: 'SkippedThreshold', failureThreshold: '0']
          ],
          tools: [[$class: 'PHPUnitJunitHudsonTestType', pattern: 'ut.xml']]
        ])
        step([
          $class: 'CloverPublisher',
          cloverReportDir: '.',
          cloverReportFileName: 'coverage.xml'
        ])
        step([
          $class: 'hudson.plugins.checkstyle.CheckStylePublisher',
          pattern: 'codestyle.xml',
          usePreviousBuildAsReference: true,
          useDeltaValues: true,
          failedNewAll: '0'
        ])
      }
      */
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Unit tests stage failure.');
    }
  }

  stage('Package') {
    parallel 'centos6': {
      node {
        sh 'cd /opt/centreon-build && git pull && cd -'
        sh '/opt/centreon-build/jobs/awie/3.4/mon-awie-package.sh centos6'
      }
    },
    'centos7': {
      node {
        sh 'cd /opt/centreon-build && git pull && cd -'
        sh '/opt/centreon-build/jobs/awie/3.4/mon-awie-package.sh centos7'
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Package stage failure.');
    }
  }

  stage('Bundle') {
    parallel 'centos6': {
      node {
        sh 'cd /opt/centreon-build && git pull && cd -'
        sh '/opt/centreon-build/jobs/awie/3.4/mon-awie-bundle.sh centos6'
      }
    },
    'centos7': {
      node {
        sh 'cd /opt/centreon-build && git pull && cd -'
        sh '/opt/centreon-build/jobs/awie/3.4/mon-awie-bundle.sh centos7'
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Bundle stage failure.');
    }
  }

  stage('Acceptance tests') {
//    parallel 'centos6': {
//      node {
//        sh 'cd /opt/centreon-build && git pull && cd -'
//        sh '/opt/centreon-build/jobs/awie/3.4/mon-awie-acceptance.sh centos6'
//        step([
//          $class: 'XUnitBuilder',
//          thresholds: [
//            [$class: 'FailedThreshold', failureThreshold: '0'],
//            [$class: 'SkippedThreshold', failureThreshold: '0']
//          ],
//          tools: [[$class: 'JUnitType', pattern: 'xunit-reports/**/*.xml']]
//        ])
//        archiveArtifacts allowEmptyArchive: true, artifacts: 'acceptance-logs/*.txt, acceptance-logs/*.png'
//      }
//    },
//    'centos7': {
//      node {
//        sh 'cd /opt/centreon-build && git pull && cd -'
//        sh '/opt/centreon-build/jobs/awie/3.4/mon-awie-acceptance.sh centos7'
//        step([
//          $class: 'XUnitBuilder',
//          thresholds: [
//            [$class: 'FailedThreshold', failureThreshold: '0'],
//            [$class: 'SkippedThreshold', failureThreshold: '0']
//          ],
//          tools: [[$class: 'JUnitType', pattern: 'xunit-reports/**/*.xml']]
//        ])
//        archiveArtifacts allowEmptyArchive: true, artifacts: 'acceptance-logs/*.txt, acceptance-logs/*.png'
//      }
//    }
//    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
//      error('Acceptance tests stage failure.');
//    }
  }

  if (env.BRANCH_NAME == 'master') {
    stage('Delivery') {
      node {
        sh 'cd /opt/centreon-build && git pull && cd -'
        sh '/opt/centreon-build/jobs/awie/3.4/mon-awie-delivery.sh'
      }
      if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
        error('Delivery stage failure.');
      }
    }
    build job: 'centreon-automation/master', wait: false
    build job: 'centreon-export/master', wait: false
  }
} catch(e) {
  if (env.BRANCH_NAME == 'master') {
  /*
    slackSend channel: "#monitoring-metrology", color: "#F30031", message: "*FAILURE*: `CENTREON AWIE` <${env.BUILD_URL}|build #${env.BUILD_NUMBER}> on branch ${env.BRANCH_NAME}\n*COMMIT*: <https://github.com/centreon/centreon-awie/commit/${source.COMMIT}|here> by ${source.COMMITTER}\n*INFO*: ${e}"
  */
  }
}
