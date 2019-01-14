stage('Source') {
  node {
    sh 'setup_centreon_build.sh'
    dir('centreon-react-components') {
      checkout scm
    }
    sh './centreon-build/jobs/react-components/react-components-source.sh'
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
    publishHTML([
      allowMissing: false,
      keepAll: true,
      reportDir: 'summary',
      reportFiles: 'index.html',
      reportName: 'Centreon Build Artifacts',
      reportTitles: ''
    ])
  }
}