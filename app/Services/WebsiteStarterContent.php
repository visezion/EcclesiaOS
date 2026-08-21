<?php

declare(strict_types=1);

namespace App\Services;

final class WebsiteStarterContent
{
    /** @return list<array<string, mixed>> */
    public function pages(string $template, string $siteName): array
    {
        $voices = [
            'modern' => [
                'home' => 'A church for worship, growth, and your next step.',
                'ministries' => 'Find a place to serve with purpose.',
                'about' => 'Discover the story, faith, and people behind our church.',
                'sermons' => 'Messages that help you live your faith every day.',
                'locations' => 'Find a welcoming church family near you.',
                'events' => 'Make room for meaningful moments together.',
                'contact' => 'We would love to welcome you.',
                'store' => 'Resources for your journey with Jesus.',
            ],
            'classic' => [
                'home' => 'A faithful church family for every season of life.',
                'ministries' => 'Serve faithfully. Grow together. Love generously.',
                'about' => 'Our faith, our story, and the people we are called to serve.',
                'sermons' => 'Biblical teaching for life and faith.',
                'locations' => 'Worship with us at a church family near you.',
                'events' => 'Gather with us for worship and fellowship.',
                'contact' => 'Plan your visit and come worship with us.',
                'store' => 'Faith-building resources for your home.',
            ],
            'community' => [
                'home' => 'Come as you are. Find your people. Belong here.',
                'ministries' => 'There is a place for you to belong and contribute.',
                'about' => 'A diverse church family learning to follow Jesus together.',
                'sermons' => 'Practical encouragement for the road ahead.',
                'locations' => 'Meet your church family in a community near you.',
                'events' => 'There is always room at the table.',
                'contact' => 'Have a question? We are here to help.',
                'store' => 'Helpful resources for your family and faith.',
            ],
            'crepa' => [
                'home' => 'Find your place in the story.',
                'ministries' => 'Find your people. Grow together.',
                'about' => 'A church with room for your questions and your calling.',
                'sermons' => 'Words for the journey.',
                'locations' => 'A place to gather, wherever you are.',
                'events' => 'Make time for community.',
                'contact' => 'We would love to meet you.',
                'store' => 'Resources for your next step.',
            ],
            'elevation' => [
                'home' => 'See what God can do through you.',
                'ministries' => 'Find your place to serve and make an eternal impact.',
                'about' => 'Discover the story and mission of our church family.',
                'sermons' => 'Messages to strengthen your faith for today.',
                'locations' => 'Join us in person or online wherever you are.',
                'events' => 'Step into what God is doing in our community.',
                'contact' => 'We are saving you a seat this Sunday.',
                'store' => 'Resources to help you grow in faith.',
            ],
            'austin-stone' => [
                'home' => 'One church family, many places to belong.',
                'ministries' => 'Grow in community and live sent together.',
                'about' => 'Love God, love the church, love the city, love the nations.',
                'sermons' => 'Biblical teaching for an integrated life with Christ.',
                'locations' => 'Find your congregation and gather with us.',
                'events' => 'Walk with Jesus through classes, groups, and events.',
                'contact' => 'You are welcome here, wherever you are in your journey.',
                'store' => 'Study guides and resources for your next step.',
            ],
            'motivation' => [
                'home' => 'Faith that moves you forward.',
                'ministries' => 'Use your gifts to love people and change lives.',
                'about' => 'A church family helping people discover purpose in Jesus.',
                'sermons' => 'Practical encouragement for your everyday faith.',
                'locations' => 'Connect with a church family near you.',
                'events' => 'Make meaningful memories with your church family.',
                'contact' => 'Come as you are and take your next step.',
                'store' => 'Tools for a faith that keeps moving.',
            ],
            'vous' => [
                'home' => 'Jesus changes everything. Come see for yourself.',
                'ministries' => 'Find community, discover purpose, and serve boldly.',
                'about' => 'A diverse family following Jesus with faith and courage.',
                'sermons' => 'Messages for a life fully alive in Christ.',
                'locations' => 'Gather with us in Miami and online around the world.',
                'events' => 'There is always something new to discover together.',
                'contact' => 'Your story matters here. We would love to meet you.',
                'store' => 'Resources for your walk with Jesus.',
            ],
            'river-valley' => [
                'home' => 'Life-changing faith in Jesus Christ.',
                'ministries' => 'A place for every generation to grow and serve.',
                'about' => 'Discover our vision to help people know God and make Him known.',
                'sermons' => 'Truth and hope for every season of life.',
                'locations' => 'Find a welcoming campus in your community.',
                'events' => 'Worship, connect, and grow together.',
                'contact' => 'We cannot wait to meet you this weekend.',
                'store' => 'Resources for a life of faith.',
            ],
            'city' => [
                'home' => 'A church for the city and the good of the world.',
                'ministries' => 'Find meaningful ways to love and serve your neighbors.',
                'about' => 'A local church learning the way of Jesus together.',
                'sermons' => 'Thoughtful teaching for faithful living.',
                'locations' => 'Gather with us in your neighborhood.',
                'events' => 'Make space for worship, friendship, and service.',
                'contact' => 'Come join us at the table.',
                'store' => 'Resources for a faithful life.',
            ],
            'anchor' => [
                'home' => 'Anchored in the gospel. Open to the neighborhood.',
                'ministries' => 'Grow deep roots and make a difference together.',
                'about' => 'A gospel-centered church for the whole family.',
                'sermons' => 'God\'s word for everyday life.',
                'locations' => 'Find your place to worship and belong.',
                'events' => 'Gather for worship, friendship, and mission.',
                'contact' => 'We would love to welcome you home.',
                'store' => 'Resources to help your roots grow deep.',
            ],
            'meeting-house' => [
                'home' => 'A generous welcome for the whole neighborhood.',
                'ministries' => 'Make friends, find support, and serve with joy.',
                'about' => 'A community learning to live the way of Jesus.',
                'sermons' => 'Hopeful teaching for real life.',
                'locations' => 'Come gather around the table with us.',
                'events' => 'There is always room for one more.',
                'contact' => 'Questions are welcome. So are you.',
                'store' => 'Helpful resources for your journey.',
            ],
            'bay-hope' => [
                'home' => 'Bright hope for every family and every season.',
                'ministries' => 'A place for kids, students, adults, and families.',
                'about' => 'Following Jesus together with joy and purpose.',
                'sermons' => 'Encouragement for the road ahead.',
                'locations' => 'Find a place to worship near you.',
                'events' => 'Connect, celebrate, and grow with us.',
                'contact' => 'We would love to help you feel at home.',
                'store' => 'Resources for growing a hopeful faith.',
            ],
            'brooklake' => [
                'home' => 'Following Jesus together.',
                'ministries' => 'Grow as disciples and live on mission together.',
                'about' => 'A church family rooted in Jesus and present in our community.',
                'sermons' => 'Biblical hope for the life you are living.',
                'locations' => 'Worship with us at a campus near you.',
                'events' => 'Join us for the next step in community.',
                'contact' => 'Plan your visit. We will be glad to see you.',
                'store' => 'Resources for following Jesus together.',
            ],
        ];
        $voice = $voices[$template] ?? $voices['modern'];

        $page = function (string $title, string $slug, string $body, array $sections, string $hero) use ($template, $voice): array {
            $design = [
                'starter' => true,
                'starter_template' => $template,
            ];

            if ($slug !== 'home') {
                $design = array_merge($design, [
                    'hero_eyebrow' => $title,
                    'hero_heading' => $voice[$hero],
                    'hero_body' => $body,
                ]);
            }

            return [
                'title' => $title,
                'slug' => $slug,
                'status' => 'published',
                'body' => $body,
                'sections' => $sections,
                'design' => $design,
                'published_at' => now(),
            ];
        };

        return [
            $page('Home', 'home', 'A welcoming church community where people can worship, grow, serve, and find hope.', ['hero', 'welcome', 'services', 'events', 'ministries', 'locations', 'sermons', 'store', 'giving', 'contact'], 'home'),
            $page('Ministries', 'ministries', 'Discover the ministries and communities helping people grow in faith, build friendships, and serve others.', ['hero', 'ministries', 'contact'], 'ministries'),
            $page('About', 'about', 'Learn about our mission, vision, faith, and the story God is writing through this church family.', ['hero', 'welcome', 'contact'], 'about'),
            $page('Sermons', 'our-sermons', 'Catch up on recent messages, explore biblical teaching, and take your next step with us.', ['hero', 'sermons', 'contact'], 'sermons'),
            $page('Our Locations', 'our-locations', 'Find a church campus near you, see service details, and plan your visit.', ['hero', 'locations', 'contact'], 'locations'),
            $page('Events', 'events', 'Join us for worship, fellowship, learning, and practical opportunities to make a difference.', ['hero', 'events', 'contact'], 'events'),
            $page('Contact', 'contact', 'Have a question, need prayer, or want to visit? We would be glad to hear from you.', ['hero', 'contact'], 'contact'),
            $page('Store', 'store', 'Find books, study guides, and other resources connected to the life and mission of our church.', ['hero', 'store', 'contact'], 'store'),
        ];
    }
}
