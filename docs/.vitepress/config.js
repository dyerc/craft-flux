export default {
  title: 'Flux',
  base: '/plugins/flux/',
  description: 'Just playing around.',
  themeConfig: {
    logo: '/icon.svg',
    socialLinks: [
      { icon: 'github', link: 'https://github.com/dyerc/craft-flux' },
    ],
    nav: [
      { text: 'Changelog', link: 'https://github.com/dyerc/craft-flux/blob/develop/CHANGELOG.md' }
    ],
    sidebar: [
      {
        text: 'Get Started',
        items: [
          { text: 'How it Works', link: '/get-started/how-flux-works' },
          { text: 'Installation', link: '/get-started/installation' },
          { text: 'Basic Setup', link: '/get-started/basic-setup' },
          { text: 'Deployment', link: '/get-started/deployment' },
          { text: 'Transforming Images', link: '/get-started/transforming-images' }
        ]
      },
      {
        items: [
          { text: 'Troubleshooting', link: '/troubleshooting' },
          { text: 'FAQ', link: '/faq' }
        ]
      }
    ]
  }
}