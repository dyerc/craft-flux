export default {
  title: 'Flux',
  base: '/plugins/flux/',
  description: 'Just playing around.',
  themeConfig: {
    logo: '/resources/icon.svg',
    socialLinks: [
      { icon: 'github', link: 'https://github.com/dyerc/flux' },
    ],
    nav: [
      { text: 'Guide', link: '/guide' },
      { text: 'Configs', link: '/configs' },
      { text: 'Changelog', link: 'https://github.com/...' }
    ],
    sidebar: [
      {
        text: 'Get Started',
        items: [
          { text: 'Installation', link: '/get-started/installation' },
          { text: 'Basic Setup', link: '/get-started/basic-setup' },
          { text: 'Deployment', link: '/get-started/deployment' },
          { text: 'Transform Parameters', link: '/get-started/transform-parameters' },
          { text: 'Configuration', link: '/get-started/configuration' }
        ]
      },
      {
        text: 'Section Title B',
        items: [
          { text: 'Item C', link: '/item-c' },
          { text: 'Item D', link: '/item-d' }
        ]
      },
      {
        items: [
          { text: 'FAQ', link: '/faq' }
        ]
      }
    ]
  }
}